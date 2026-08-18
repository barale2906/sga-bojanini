<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Mail;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExpenseOrderAccountingMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<array{name: string, mime: string, data: string}> $extraAttachments
     */
    public function __construct(
        public readonly PurchaseOrderModel $order,
        public readonly array $extraAttachments = [],
    ) {}

    /**
     * @param UploadedFile[] $files
     * @return static
     */
    public static function withFiles(PurchaseOrderModel $order, array $files): static
    {
        $prepared = array_map(fn (UploadedFile $f) => [
            'name' => $f->getClientOriginalName(),
            'mime' => $f->getMimeType() ?? 'application/octet-stream',
            'data' => base64_encode(file_get_contents($f->getRealPath())),
        ], $files);

        return new static($order, $prepared);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "OC Gastos {$this->order->code} — Factura {$this->order->invoice_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.expense_order_accounting',
            with: ['order' => $this->order],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.expense_order', ['order' => $this->order])
            ->setPaper('letter', 'portrait');

        $attachments = [
            Attachment::fromData(
                fn () => $pdf->output(),
                "orden-{$this->order->code}.pdf",
            )->withMime('application/pdf'),
        ];

        foreach ($this->extraAttachments as $file) {
            $attachments[] = Attachment::fromData(
                fn () => base64_decode($file['data']),
                $file['name'],
            )->withMime($file['mime']);
        }

        return $attachments;
    }
}
