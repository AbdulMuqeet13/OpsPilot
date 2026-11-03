<?php

namespace App\Services\Tickets;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Parses a pasted raw email (headers, a blank line, then the body) into ticket fields.
 * Handles folded headers, "Name <address>" senders, quoted-printable / base64 bodies
 * and the plain-text part of simple multipart messages.
 */
class InboundEmailParser
{
    /**
     * @return array{name: string, email: string, subject: string, message: string}
     *
     * @throws ValidationException
     */
    public function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", trim($raw));
        [$headerBlock, $body] = array_pad(preg_split("/\n\s*\n/", $raw, 2) ?: [], 2, '');

        $headers = $this->headers($headerBlock);
        [$name, $email] = $this->sender($headers['from'] ?? '');

        $message = $this->body($headers, $body);
        $subject = trim($this->decodeHeader($headers['subject'] ?? ''));

        if ($email === null) {
            throw ValidationException::withMessages(['raw_email' => 'We couldn\'t find a valid "From:" address in the email headers.']);
        }

        if ($message === '') {
            throw ValidationException::withMessages(['raw_email' => 'The email has no readable text body.']);
        }

        return [
            'name' => Str::limit($name ?: Str::before($email, '@'), 120, ''),
            'email' => $email,
            'subject' => Str::limit($subject !== '' ? $subject : Str::words($message, 8, '…'), 200, ''),
            'message' => Str::limit($message, 10000, ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $block): array
    {
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $block) ?? $block;
        $headers = [];

        foreach (explode("\n", $unfolded) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function sender(string $from): array
    {
        $from = $this->decodeHeader($from);

        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $from, $matches) === 1) {
            $name = trim($matches[1]);
            $address = trim($matches[2]);
        } else {
            $name = '';
            $address = trim($from);
        }

        return [$name, filter_var($address, FILTER_VALIDATE_EMAIL) ? strtolower($address) : null];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function body(array $headers, string $body): string
    {
        $contentType = $headers['content-type'] ?? 'text/plain';

        if (preg_match('/multipart\/[a-z]+;.*boundary="?([^";]+)"?/i', $contentType, $matches) === 1) {
            foreach (explode('--'.$matches[1], $body) as $part) {
                [$partHeaderBlock, $partBody] = array_pad(preg_split("/\n\s*\n/", trim($part), 2) ?: [], 2, '');
                $partHeaders = $this->headers($partHeaderBlock);

                if (str_contains(strtolower($partHeaders['content-type'] ?? ''), 'text/plain')) {
                    return $this->decodeBody($partHeaders, $partBody);
                }
            }
        }

        return $this->decodeBody($headers, $body);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function decodeBody(array $headers, string $body): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');

        $decoded = match ($encoding) {
            'quoted-printable' => quoted_printable_decode($body),
            'base64' => (string) base64_decode($body, true),
            default => $body,
        };

        $text = str_contains(strtolower($headers['content-type'] ?? ''), 'text/html')
            ? html_entity_decode(strip_tags($decoded))
            : $decoded;

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private function decodeHeader(string $value): string
    {
        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }
}
