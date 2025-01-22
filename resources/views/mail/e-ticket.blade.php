@php
if(!function_exists('formatTanggal')) {
    function formatTanggal($date) {
        $days = [
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => "Jum'at",
            'Sat' => 'Sabtu',
            'Sun' => 'Minggu',
        ];

        $months = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

        $expDate = explode('-', $date);
        return $days[ date('D', strtotime($date)) ] . ', ' . $expDate[2] . ' ' . $months[ $expDate[1] ] . ' ' . $expDate[0];
    }
}
@endphp

<html lang="en">
<head>
    <style>
        .col-1, .col-3 { width: 320px; }
        .col-2 { 
            width: 250px;
            border-left: 2px solid #8C8C8C;
            border-right: 2px solid #8C8C8C;
            margin-left: 15px;
            margin-right: 15px; 
        }
    </style>
</head>
<body>
    <div style="
        border: 2px solid #595959; 
        border-radius: 8px;
        padding: 15px;
        width: 1000px;
        text-align: center;
        margin: auto;
        font-family: kanit;
    ">
        <div class="col-1" style="
            float: left;
            vertical-align: middle;
        ">
            <img src="{!! $onlineTicket['cover_image'] !!}" style="
                border-top-left-radius: 8px; 
                border-top-right-radius: 8px;
                width: 100%;
                margin-bottom: 5px;
            ">

            <div style="border-radius: 8px; border: 1px solid #595959;">
                <div style="
                    text-align: center;
                    font-weight: 600;
                    text-transform: uppercase;
                    padding: 10px;
                    background-color: #EB4D2D;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    color: white;
                    font-size: 16px;
                ">
                    PERHATIAN !
                </div>
                <div style="padding: 15px 15px; font-size: 13px; text-align: left;">
                    <p style="margin-top: 0;">Lembar ini merupakan <b>E-Ticket / Tiket Elektronik</b> Anda.</p>
                    
                    @if($onlineTicket['type'] == 'offline')
                        <p style="margin: 0; margin-top: 10px;">Lembar ini <b>wajib Diperlihatkan</b> atau <b>dicetak</b> saat akan memasuki tempat acara.</p>
                    @else
                        <p style="margin: 0; margin-top: 10px;">Link tersebut adalah <b>link ticket</b> yang telah anda beli, harap <b>masuk dengan link tersebut</b> untuk bergabung.</p>
                    @endif
                </div>
            </div>
        </div>

        @if($onlineTicket['type'] == 'offline')
            <div class="col-2" style="
                float: left;
                text-align: center;
                vertical-align: middle;
                padding: 10px;
            ">
                @if($onlineTicket['ticket_pass'])
                    @foreach($onlineTicket['ticket_pass_item'] as $j => $valueTicketPass)
                        <p style="font-size: 14px; font-weight: bold; margin: 0; margin-top: {!! $j > 0 ? '15px' : '0' !!};">
                            {!! $valueTicketPass['title'] !!}
                        </p>

                        <p style="font-size: 13px; margin: 0;">
                            @if(!is_null($valueTicketPass['date']))
                                {!! formatTanggal($valueTicketPass['date']) !!} <br>
                                {!! date('H:i', strtotime($valueTicketPass['date'] . ' ' . $valueTicketPass['start_time'])) !!} - {!! date('H:i', strtotime($valueTicketPass['date'] . ' ' . $valueTicketPass['end_time'])) !!} WIB
                            @endif
                        </p>
                    @endforeach

                @else
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={!! $onlineTicket['qr_code'] !!}" style="max-width: 100%;">
                @endif
            </div>

        @else
            <div class="col-2" style="
                float: left;
                text-align: center;
                vertical-align: middle;
                padding: 15px;
            ">
                @if($onlineTicket['ticket_pass'])
                    @foreach($onlineTicket['ticket_pass_item'] as $j => $valueTicketPass)
                        <p style="font-size: 14px; font-weight: bold; margin: 0; margin-top: {!! $j > 0 ? '15px' : '0' !!};">
                            @if(!is_null($valueTicketPass['date']))
                                {!! formatTanggal($valueTicketPass['date']) !!} <br>
                                {!! date('H:i', strtotime($valueTicketPass['date'] . ' ' . $valueTicketPass['start_time'])) !!} - {!! date('H:i', strtotime($valueTicketPass['date'] . ' ' . $valueTicketPass['end_time'])) !!} WIB
                            @endif
                        </p>
                        <a target="_blank" href="{{ $valueTicketPass['url_meeting'] }}" style="
                            font-size: 12px;
                            display: block;
                            margin-top: 3px;
                            color: #5e72e4;
                            text-decoration: none;
                        ">{!! $valueTicketPass['url_meeting'] !!}</a>
                    @endforeach

                @else
                    <p style="font-size: 14px; font-weight: bold; margin: 0;">
                        @if(!is_null($onlineTicket['date']))
                            {!! formatTanggal($onlineTicket['date']) !!} <br>
                            {!! date('H:i', strtotime($onlineTicket['date'] . ' ' . $onlineTicket['start_time'])) !!} - {!! date('H:i', strtotime($onlineTicket['date'] . ' ' . $onlineTicket['end_time'])) !!} WIB
                        @endif
                    </p>
                    <a target="_blank" href="{{ $onlineTicket['url_meeting'] }}" style="
                        font-size: 12px;
                        display: block;
                        margin-top: 3px;
                        color: #5e72e4;
                        text-decoration: none;
                    ">{!! $onlineTicket['url_meeting'] !!}</a>
                @endif
            </div>
        @endif
        
        <div class="col-3" style="
            float: left;
            border-radius: inherit;
            border: 1px solid #595959;
            padding: 15px 10px;
            text-align: center;
            vertical-align: middle;
            padding: 15px;
            margin-left: 20px;
        ">
            <h1 style="
                font-size: 20px; 
                font-weight: 800;
                margin: 0;
                line-height: 1.3;
            ">
                {!! $onlineTicket['title'] !!}
            </h1>

            @if($onlineTicket['type'] == 'offline')
                <p style="font-size: 14px; color: #8C8C8C;">
                    {!! $onlineTicket['address'] !!}
                </p>
            @endif

            <div>
                <p style="
                    font-weight: 600;
                    background-color: #BFBFBF;
                    color: #1F1F1F;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    font-size: 14px;
                    padding: 4px;
                    margin-bottom: 0;
                ">Nama Pelanggan</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 5px;
                    word-break: break-word;
                ">{!! $onlineTicket['customer_name'] !!}</p>
            </div>
            
            <div>
                <p style="
                    font-weight: 600;
                    background-color: #BFBFBF;
                    color: #1F1F1F;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    font-size: 14px;
                    padding: 4px;
                    margin-bottom: 0;
                ">ID Transaksi</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 5px;
                    word-break: break-word;
                ">{!! $onlineTicket['order_id'] !!}</p>
            </div>
            
            <div>
                <p style="
                    font-weight: 600;
                    background-color: #BFBFBF;
                    color: #1F1F1F;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    font-size: 14px;
                    padding: 4px;
                    margin-bottom: 0;
                ">Harga Tiket</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 5px;
                    word-break: break-word;
                ">Rp. {!! number_format($onlineTicket['price'], 0, ',', '.') !!}</p>
            </div>
        </div>

        <div style="clear: both; margin: 0; padding: 0;"></div>
    </div>    
</body>
</html>