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
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap');

        * {
            font-family: 'Kanit', sans-serif;
        }

        .col-1, .col-2, .col-3 { width: 240px; }

        @media only screen and (max-width: 480px) {     
            .col-1, .col-3 {
                width: 290px;
            }

            .col-2 {
                border-top: 2px solid #8C8C8C;
                border-bottom: 2px solid #8C8C8C;
                margin: 30px 0;
                padding: 20px 0;
                width: 230px;
            }
        }
        
        @media only screen and (min-width: 480px) {            
            .col-2 {
                border-left: 2px solid #8C8C8C;
                border-right: 2px solid #8C8C8C;
                margin-left: 15px;
                margin-right: 15px;
            }
        }

        .btn-course {
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            padding: 8px 12px;
            background-color: #5e72e4;
            color: #ffffff!important;
            border-radius: 8px;
            font-size: 14px;
            margin: 7px auto;
            display: inline-block;
        }
    </style>
</head>
<body>
    @foreach($onlineTicket as $i => $valueTicket)
        <div style="
            border: 2px solid #595959; 
            border-radius: 8px;
            padding: 8px;
            max-width: 900px;
            text-align: center;
            margin: auto;
            {!! $i > 0 ? 'margin-top: 30px;' : '' !!}
        ">
            <div class="col-1" style="
                display: inline-block;
                vertical-align: middle;
            ">
                <img src="{!! $message->embed($valueTicket['cover_image'], 'Cover Image') !!}" style="
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    width: 100%;
                ">

                <div style="border-radius: 8px; border: 1px solid #595959;">
                    <div style="
                        text-align: center;
                        font-weight: 600;
                        text-transform: uppercase;
                        padding: 10px;
                        background-color: #EB4D2D;
                        border-top-left-radius: inherit;
                        border-top-right-radius: inherit;
                        color: white;
                        font-size: 16px;
                    ">
                        PERHATIAN !
                    </div>
                    <div style="padding: 15px 15px; font-size: 13px; text-align: left;">
                        <p style="margin-top: 0;">Lembar ini merupakan <b>E-Ticket / Tiket Elektronik</b> Anda.</p>
                        
                        @if(isset($valueTicket['type']) && $valueTicket['type'] == 'offline')
                            <p style="margin: 0; margin-top: 10px;">Lembar ini <b>wajib Diperlihatkan</b> atau <b>dicetak</b> saat akan memasuki tempat acara.</p>
                        @elseif(isset($valueTicket['online_course_id']))
                            <p style="margin: 0; margin-top: 10px;">Link tersebut adalah <b>link online course</b> yang telah anda beli, harap <b>masuk dengan link tersebut</b> untuk bergabung.</p>
                        @else
                            <p style="margin: 0; margin-top: 10px;">Link tersebut adalah <b>link ticket</b> yang telah anda beli, harap <b>masuk dengan link tersebut</b> untuk bergabung.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-2" style="
                display: inline-block;
                text-align: center;
                vertical-align: middle;
                padding: 10px;
            ">
                @if(isset($valueTicket['type']) && $valueTicket['type'] == 'offline')
                    @if($valueTicket['ticket_pass'])
                        @foreach($valueTicket['ticket_pass_item'] as $j => $valueTicketPass)
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
                        @php $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={$valueTicket['qr_code']}"; @endphp
                        <img src="{!! $message->embed($qrCode, 'QR Code') !!}" style="max-width: 100%;">
                    @endif

                @elseif(isset($valueTicket['online_course_id']))
                    <h3 style="
                        margin: 0;
                        line-height: 1.2;
                        font-size: 16px;
                        font-weight: 600;
                        margin-bottom: 5px;
                    ">
                        {!! $valueTicket['instructor_name'] !!}
                    </h3>

                    <p style="font-size: 13px; margin: 0; margin-bottom: 8px;">
                        {!! $valueTicket['duration'] !!}
                    </p>

                    <a target="_blank" href="{!! $valueTicket['url_course'] !!}" class="btn-course">Buka Tautan</a>

                @else
                    @if($valueTicket['ticket_pass'])
                        @foreach($valueTicket['ticket_pass_item'] as $j => $valueTicketPass)
                            <p style="font-size: 14px; font-weight: 600; margin: 0; margin-top: {!! $j > 0 ? '15px' : '0' !!};">
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
                        <p style="font-size: 14px; font-weight: 600; margin: 0;">
                            @if(!is_null($valueTicket['date']))
                                {!! formatTanggal($valueTicket['date']) !!} <br>
                                {!! date('H:i', strtotime($valueTicket['date'] . ' ' . $valueTicket['start_time'])) !!} - {!! date('H:i', strtotime($valueTicket['date'] . ' ' . $valueTicket['end_time'])) !!} WIB
                            @endif
                        </p>

                        <a target="_blank" href="{{ $valueTicket['url_meeting'] }}" style="
                            font-size: 12px;
                            display: block;
                            margin-top: 3px;
                            color: #5e72e4;
                            text-decoration: none;
                        ">{!! $valueTicket['url_meeting'] !!}</a>
                    @endif
                @endif
            </div>
            
            <div class="col-3" style="
                display: inline-block;
                border-radius: inherit;
                border: 1px solid #595959;
                padding: 15px 10px;
                text-align: center;
                vertical-align: middle;
                padding: 15px;
            ">
                <h1 style="
                    font-size: 20px; 
                    font-weight: 800;
                    margin: 0;
                    line-height: 1.3;
                ">
                    {!! $valueTicket['title'] !!}
                </h1>

                @if(isset($valueTicket['type']) && $valueTicket['type'] == 'offline')
                    <p style="font-size: 14px; color: #8C8C8C;">
                        {!! $valueTicket['address'] !!}
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
                        padding: 4px 0;
                        margin-bottom: 0;
                    ">Nama Pelanggan</p>
                    <p style="
                        background-color: #FAFAFA;
                        color: #1F1F1F;
                        font-size: 12px;
                        margin: 0;
                        padding: 4px 0;
                    ">{!! $valueTicket['customer_name'] !!}</p>
                </div>
                
                <div>
                    <p style="
                        font-weight: 600;
                        background-color: #BFBFBF;
                        color: #1F1F1F;
                        border-top-left-radius: 8px;
                        border-top-right-radius: 8px;
                        font-size: 14px;
                        padding: 4px 0;
                        margin-bottom: 0;
                    ">ID Transaksi</p>
                    <p style="
                        background-color: #FAFAFA;
                        color: #1F1F1F;
                        font-size: 12px;
                        margin: 0;
                        padding: 4px 0;
                    ">{!! $valueTicket['order_id'] !!}</p>
                </div>
                
                <div>
                    <p style="
                        font-weight: 600;
                        background-color: #BFBFBF;
                        color: #1F1F1F;
                        border-top-left-radius: 8px;
                        border-top-right-radius: 8px;
                        font-size: 14px;
                        padding: 4px 0;
                        margin-bottom: 0;
                    ">{{ isset($valueTicket['online_course_id']) ? 'Course' : 'Ticket' }} Price</p>
                    <p style="
                        background-color: #FAFAFA;
                        color: #1F1F1F;
                        font-size: 12px;
                        margin: 0;
                        padding: 4px 0;
                    ">Rp. {!! number_format($valueTicket['price'], 0, ',', '.') !!}</p>
                </div>
            </div>
        </div>    
    @endforeach
</body>
</html>