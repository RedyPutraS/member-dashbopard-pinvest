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

        $expDatetime = explode(' ', $date);
        $expDate = explode('-', $expDatetime[0]);
        return $days[ date('D', strtotime($date)) ] . ', ' . $expDate[2] . ' ' . $months[ $expDate[1] ] . ' ' . $expDate[0] . ' | ' . date('H:i', strtotime($expDatetime[1])) . ' WIB';
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

        .col-1 { width: 300px; }
        .col-2 { width: 260px; }

        @media only screen and (max-width: 480px) {     
            .col-1 {
                width: 290px;
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
    <div style="
        border: 2px solid #595959; 
        border-radius: 8px;
        padding: 8px;
        max-width: {!! count($surveyResult) > 0 ? '650px;' : '350px;' !!}
        text-align: center;
        margin: auto;
    ">
        <div class="col-1" style="
            display: inline-block;
            vertical-align: top;
        ">
            <img src="{!! env('PINVEST_LOGO_URL') !!}" style="
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                height: 100px;
                margin-bottom: 10px;
            ">
            <p style="
                margin-top: 0; 
                text-align: justify; 
                font-size: 12px; 
                font-weight: normal;
                margin-left: 5px;
                margin-right: 5px;
                margin-bottom: 15px;
            ">
                Permintaan baru inquiry baru saja dikirim, berikut dibawah informasi tentang inquiry yang dibuat.
                {!! count($surveyResult) > 0 ? 'Dibagian kanan terdapat hasil dari survey pertanyaan yang telah dijawab oleh pengirim.' : '' !!}
            </p>

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
                ">Nama</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['user_name'] !!}</p>
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
                ">Email</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 11px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['email'] !!}</p>
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
                ">No Handphone</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 11px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['no_telp'] !!}</p>
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
                ">Kanal</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['app'] !!}</p>
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
                ">Judul Kanal</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['source'] !!}</p>
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
                ">Tanggal Pengajuan</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 12px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! formatTanggal($inquiryData['submission_date']) !!}</p>
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
                ">Catatan</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 11px;
                    margin: 0;
                    padding: 6px 0;
                ">{!! $inquiryData['notes'] !!}</p>
            </div>
        </div>

        @if(count($surveyResult) > 0)
            <div class="col-2" style="
                display: inline-block;
                border-radius: inherit;
                padding: 15px 10px;
                text-align: center;
                vertical-align: top;
                padding-left: 25px;
                padding-top: 20px;
            ">
                <p style="
                    font-weight: 600;
                    background-color: #EB4D2D;
                    color: white;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    font-size: 15px;
                    padding: 4px 0;
                    margin-bottom: 0;
                ">Survey Result</p>

                @foreach($surveyResult as $key => $value)
                    <div {!! $key > 0 ? 'style="margin-top: 10px;"' : '' !!}>
                        <p style="
                            font-weight: 600;
                            color: #1F1F1F;
                            font-size: 12px;
                            margin-bottom: 5px;
                            text-align: left;
                        ">
                            <span style="background-color: #BFBFBF; padding: 0 4px; color: #1F1F1F; margin-right: 4px;">Q</span>
                            {!! $value['question'] !!}
                        </p>
                        <p style="
                            color: #1F1F1F;
                            font-size: 12px;
                            margin: 0;
                            text-align: left;
                        ">
                            <span style="background-color: #5e72e4; padding: 0 4px; color: white; margin-right: 4px;">A</span>
                            {!! $value['answer'] !!}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>    
</body>
</html>