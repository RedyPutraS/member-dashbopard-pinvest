<?php

namespace App\Mail;

use App\Models\DetailEvent;
use App\Models\MappingApp;
use App\Models\MembershipDuration;
use App\Models\MembershipVoucher;
use App\Models\Syarat_dan_ketentuan_tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotifMembershipMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $membership;
    public $contentExclusive;
    public $voucher;
    public $membershipApps;
    public $membershipTerms;
    public $membershipDuration;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $membership, $contentExclusive) 
    {
        $this->user = $user;
        $this->membership = $membership;
        $this->contentExclusive = $contentExclusive;
        $this->membershipDuration = MembershipDuration::getDurationNameFormat($membership['type'], $membership['duration'], 'ID');
        
        $this->voucher = MembershipVoucher::select(['voucher.exp_date', 'voucher.limit', 'voucher.type', 'voucher.discount', 'voucher.max_discount', 'voucher.voucher_number'])
            ->join('voucher', 'voucher.id', 'membership_voucher.voucher_id')
            ->where('membership_plan_id', $membership['membership_plan_id'])
            ->where('voucher.status', 'active')
            ->orderBy('membership_voucher.id', 'DESC')->get()->toArray();

        $this->membershipApps = MappingApp::select('master_app.app_name')
            ->join('master_app', 'master_app.id', 'mapping_app.master_app_id')
            ->where('membership_plan_id', $membership['membership_plan_id'])
            ->orderBy('mapping_app.id', 'DESC')->get()->pluck('app_name')->toArray();

        $this->membershipTerms = Syarat_dan_ketentuan_tiket::select('content')
            ->where('id_membership',$membership['membership_plan_id'])
            ->get()->pluck('content')->toArray();
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Notifikasi Langganan Membership',
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
            view: 'mail.notif-membership',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
