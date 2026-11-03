<?php

namespace App\Http\Responses;

use App\Services\AI\AiException;
use App\Services\AI\Data\ChatResult;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams AI text to the browser as Server-Sent Events:
 * "delta" ({text}) for each piece, then "done" ({text, usageLogId}) or "error" ({message}).
 */
final class AiTextStream
{
    /**
     * The closure receives the callback to pass to AiGateway::streamChat(). It may return the
     * ChatResult alone, or [ChatResult, extra data] to add fields to the "done" event.
     *
     * @param  Closure(Closure(string): void): (ChatResult|array{0: ChatResult, 1: array<string, mixed>})  $run
     */
    public static function response(Closure $run): StreamedResponse
    {
        return response()->stream(function () use ($run): void {
            try {
                $outcome = $run(fn (string $text) => self::send('delta', ['text' => $text]));
                [$result, $extra] = $outcome instanceof ChatResult ? [$outcome, []] : $outcome;

                self::send('done', ['text' => $result->text, 'usageLogId' => $result->usageLogId, ...$extra]);
            } catch (AiException $exception) {
                self::send('error', ['message' => $exception->userMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function send(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
