<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketMessage $reply,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $workspace = $this->ticket->workspace;

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $workspace->brand_name),
            replyTo: $workspace->support_email ? [new Address($workspace->support_email, $workspace->brand_name)] : [],
            subject: "Re: {$this->ticket->subject} [{$this->ticket->reference()}]",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ticket-reply',
            with: [
                'brandName' => $this->ticket->workspace->brand_name,
                'body' => $this->reply->body,
                'reference' => $this->ticket->reference(),
            ],
        );
    }
}
