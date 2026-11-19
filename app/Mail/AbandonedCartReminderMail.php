<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public AbandonedCart $abandonedCart,
        public ?int $reminderStep = null,
    ) {}

    public function envelope(): Envelope
    {
        $step = $this->reminderStep ?? ((int) $this->abandonedCart->reminder_count + 1);
        $subject = $step >= 2
            ? 'Tus favoritos botánicos aún te esperan en Glamora Perú'
            : 'Dejaste productos en tu bolsa Glamora Perú';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $items = is_array($this->abandonedCart->cart_payload)
            ? array_values(array_filter($this->abandonedCart->cart_payload, 'is_array'))
            : [];

        return new Content(
            view: 'emails.abandoned-cart-reminder',
            with: [
                'cart' => $this->abandonedCart,
                'items' => $items,
                'cartUrl' => route('cart.index'),
                'catalogUrl' => route('catalog.index'),
                'homeUrl' => route('home'),
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
