<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Mail;

use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MovementDocumentMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly MovementDocumentModel $document,
    ) {}

    public function envelope(): Envelope
    {
        $typeLabel = $this->documentTypeLabel($this->document->document_type);

        return new Envelope(
            subject: "Comprobante {$typeLabel} — {$this->document->document_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.movement_document',
            with: [
                'document'  => $this->document,
                'typeLabel' => $this->documentTypeLabel($this->document->document_type),
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.movement_voucher', ['document' => $this->document])
            ->setPaper('letter', 'portrait');

        $filename = 'comprobante-' . $this->document->document_number . '.pdf';

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $pdf->output(),
                $filename,
            )->withMime('application/pdf'),
        ];
    }

    private function documentTypeLabel(string $type): string
    {
        return match ($type) {
            'entry'               => 'Entrada',
            'exit'                => 'Salida',
            'transfer'            => 'Traslado',
            'adjustment'          => 'Ajuste',
            'return'              => 'Devolución',
            'expiration_write_off' => 'Baja por Vencimiento',
            'loss'                => 'Baja de Inventario',
            default               => ucfirst($type),
        };
    }
}
