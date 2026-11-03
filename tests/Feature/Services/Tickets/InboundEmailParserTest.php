<?php

use App\Services\Tickets\InboundEmailParser;
use Illuminate\Validation\ValidationException;

it('reads the sender, subject and plain-text body', function () {
    $parsed = app(InboundEmailParser::class)->parse("From: Priya Raman <Priya@Example.com>\nSubject: Where is my table?\n\nHello,\n\nIt has not arrived.");

    expect($parsed)->toBe([
        'name' => 'Priya Raman',
        'email' => 'priya@example.com',
        'subject' => 'Where is my table?',
        'message' => "Hello,\n\nIt has not arrived.",
    ]);
});

it('decodes quoted-printable bodies and encoded headers', function () {
    $parsed = app(InboundEmailParser::class)->parse(
        "From: =?UTF-8?Q?Javier_Morales?= <javier@example.net>\nSubject: =?UTF-8?Q?Silla_rota?=\nContent-Transfer-Encoding: quoted-printable\n\nAyer recib=C3=AD las sillas."
    );

    expect($parsed['name'])->toBe('Javier Morales')
        ->and($parsed['subject'])->toBe('Silla rota')
        ->and($parsed['message'])->toBe('Ayer recibí las sillas.');
});

it('uses the plain-text part of a multipart email', function () {
    $raw = <<<'EML'
From: sam@example.com
Subject: Order question
Content-Type: multipart/alternative; boundary="b1"

--b1
Content-Type: text/plain; charset=UTF-8

Plain version here.
--b1
Content-Type: text/html; charset=UTF-8

<p>HTML version here.</p>
--b1--
EML;

    $parsed = app(InboundEmailParser::class)->parse($raw);

    expect($parsed['message'])->toBe('Plain version here.')
        ->and($parsed['name'])->toBe('sam');
});

it('falls back to the first eight words of the body when there is no subject', function () {
    $parsed = app(InboundEmailParser::class)->parse("From: sam@example.com\n\nMy wardrobe door will not close properly after assembly");

    expect($parsed['subject'])->toBe('My wardrobe door will not close properly after…');
});

it('rejects an email without a valid sender', function () {
    expect(fn () => app(InboundEmailParser::class)->parse("Subject: Hi\n\nNo sender here."))
        ->toThrow(ValidationException::class);
});

it('rejects an email without a body', function () {
    expect(fn () => app(InboundEmailParser::class)->parse("From: sam@example.com\nSubject: Hi\n\n   "))
        ->toThrow(ValidationException::class);
});
