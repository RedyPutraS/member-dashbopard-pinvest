<?php

namespace App\Mail;

use App\Models\DetailEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotifTransaksiMail extends Mailable
{
    use Queueable, SerializesModels;

    public $onlineTicket;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($onlineTicket) 
    {
        $this->onlineTicket = $onlineTicket;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Notifikasi Transaksi',
            from: new Address(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'mail.notif-transaksi',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        $eTicket = [];
        foreach($this->onlineTicket as $onlineTicket) {
            if(isset($onlineTicket['detail_event_id'])) {
                $eTicket[] = $onlineTicket;
            }
        }

        if(count($eTicket) > 0) {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8', 
                'fontDir' => [
                    storage_path('pdf-fonts'),
                ],
                'fontdata' => [
                    'kanit' => [
                        'R' => 'Kanit-Regular.ttf',
                        'I' => 'Kanit-Italic.ttf',
                        'B' => 'Kanit-Bold.ttf',
                        'BI' => 'Kanit-BoldItalic.ttf',
                    ]
                ],
                'default_font' => 'kanit',
		'tempDir' => __DIR__ . '/custom/temp/dir/path',
		'curlAllowUnsafeSslRequests' => true

            ]);

            foreach($eTicket as $valueTicket) {
                $view = view('mail/e-ticket')->with(['onlineTicket' => $valueTicket]);
                $mpdf->AddPage('auto');
                $mpdf->WriteHTML($view);
            }

            $orderId = str_replace('/', '_', $this->onlineTicket[0]['order_id']);
            $pathFile = storage_path('tmp/' . $orderId . '.pdf');

            $mpdf->OutputFile($pathFile);
            return Attachment::fromPath($pathFile)->as($orderId . '.pdf');
        }

        return [];
    }
}
