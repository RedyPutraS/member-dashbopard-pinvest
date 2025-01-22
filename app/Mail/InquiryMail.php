<?php

namespace App\Mail;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;

class InquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inquiryData;
    public $files;
    public $surveyResult;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($inquiryData, $files, $surveyResult = [])
    {
        $this->inquiryData = $inquiryData;
        $this->files = $files;
        $this->surveyResult = $surveyResult;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Pemberitahuan Permintaan',
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
            view: 'mail.inquiry',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        $attach = [];
        if(count($this->files) > 0) {
            foreach($this->files as $file) {
                $attach[] = Attachment::fromPath($file['url_oss'])->as($file['file_name']);
            }
        }

        return $attach;
    }
}
