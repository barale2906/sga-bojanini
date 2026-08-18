<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Mail;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExpenseOrderSupplierMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PurchaseOrderModel $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Orden de compra {$this->order->code} — Centro Dermatológico Bojanini",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.expense_order_supplier',
            with: ['order' => $this->order],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.expense_order', ['order' => $this->order])
            ->setPaper('letter', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "orden-{$this->order->code}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
