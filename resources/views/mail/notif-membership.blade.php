@php
function formatTanggal($date) {
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
    return $expDate[2] . ' ' . $months[ $expDate[1] ] . ' ' . $expDate[0];
}
@endphp

<html lang="en">
<head>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');

        * {
            font-family: 'Kanit', sans-serif;
        }

        .btn-verifikasi {
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            padding: 8px 12px;
            background-color: #5e72e4;
            color: white;
            border-radius: 8px;
            font-size: 14px;
            margin: 7px auto;
            display: inline-block;
        }

        .benefit-title {
            font-weight: 600;
            background-color: #EB4D2D;
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            font-size: 15px;
            padding: 4px 0;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        .voucher-item-desc-left {
            font-size: 12px; 
            margin: 0; 
            float: left;
        }

        .voucher-item-desc-right {
            font-size: 12px; 
            margin: 0; 
            float: right;
        }

        .voucher-item-code {
            text-align: center; 
            font-weight: bold;
            font-size: 14px; 
            margin: 15px 0;
        }

        .list-tos {
            font-size: 12px; 
            color: #4f4f4f; 
            padding-left: 20px; 
            text-align: left;
        }

        .content-exclusive-title {
            font-weight: bold; 
            font-size: 15px; 
            margin: 0;
        }

        .content-exclusive-img {
            width: 180px;
            border-radius: 8px;
        }

        .container {
            border: 2px solid #595959; 
            border-radius: 8px;
            padding: 15px 30px;
            margin: auto;
            text-align: center;
            max-width: 450px;
        }

        @media(max-width: 480px) {
            .container { padding: 15px 20px; }

            .voucher-item-desc-right { float: left!important; }
            .voucher-item-code { margin: 20px 0!important; }

            .content-exclusive-title { font-size: 14px; }
            .content-exclusive-img { width: 140px; }
        }
    </style>
</head>
<body>
    <div class="container">  
        <img src="{!! env('PINVEST_LOGO_URL') !!}" style="
            height: 100px;
            margin-bottom: 10px;
        ">

        <h1 style="
            font-size: 18px; 
            font-weight: 800;
            margin: 0;
        ">
            Selamat, Langganan Membership anda Telah Aktif!
        </h1>

        <div style="text-align: justify;">
            <p style="font-size: 14px; color: #4f4f4f;">
                Kepada YTH <b>{!! $user['first_name'] . ' ' . $user['last_name'] !!}</b>, selamat permintaan langganan membership anda telah aktif. Anda dapat segera menikmati manfaat dari paket membership yang anda pilih.
                Langganan membership anda aktif hingga <b>{!! formatTanggal($user['membership_exp']) !!}</b> (terhitung <b>{!! $membershipDuration !!}</b> dari hari ini).
            </p>

            <p style="font-size: 14px; color: #4f4f4f;"> 
                Paket yang anda pilih adalah <b>{!! $membership['plan_name'] !!}</b> memiliki manfaat sebagai berikut:
                <ul style="font-size: 14px; color: #4f4f4f; padding-left: 30px;">
                    <li>Limit Inquiry hingga <b>{!! number_format($membership['limit_inquiry'], 0, ',', '.') !!}</b></li>
                    <li>Dapat submit Inquiry di <b>{!! $membership['allow_all_apps'] ? 'Semua Layanan' : implode(', ', $membershipApps) !!}</b></li>

                    @if(count($contentExclusive) > 0)
                        <li><b>{!! number_format(count($contentExclusive), 0, ',', '.') !!}</b> Konten Eksklusif</li>
                    @endif

                    @if(count($voucher) > 0)
                        <li>Mendapatkan <b>{!! number_format(count($voucher), 0, ',', '.') !!}</b> total voucher potongan harga yang dapat anda gunakan untuk bertransaksi.</li>
                    @endif
                </ul>
            </p>
        </div>

        @if(count($contentExclusive) > 0)
            <p class="benefit-title">Konten Eksklusif</p>

            @foreach($contentExclusive as $key => $value)
                <div style="text-align: left; display: flex; margin-top: {!! $key > 0 ? '10px' : '0' !!};">
                    <div>
                        <div style="
                            padding: 5px 10px;
                            color: rgba(20, 78, 132, 1);
                            background-color: rgba(20, 78, 132, 0.25);
                            font-weight: bold;
                            border-radius: 6px;
                            font-size: 12px;
                            text-align: center;
                            margin-bottom: 5px;
                        ">{!! $value['type'] !!}</div>

                        <img src="{!! $message->embed($value['thumbnail_image'], 'Cover Image') !!}" class="content-exclusive-img">
                    </div>

                    <div style="margin-left: 15px;">
                        <h4 class="content-exclusive-title">{!! $value['title'] !!}</h4>
                        <p style="margin-bottom: 0; margin-top: 5px; font-size: 13px;">Rp. {!! number_format($value['price'], 0, ',', '.') !!}</p>
                    </div>
                </div>
            @endforeach
        @endif

        @if(count($voucher) > 0)
            <p class="benefit-title">Voucher Potongan Harga</p>

            @foreach($voucher as $key => $value)
                <div style="
                    border: 2px solid rgba(20, 78, 132, 0.25);
                    border-radius: 8px;
                    padding: 10px 20px;
                    margin-top: {!! $key > 0 ? '10px' : '0' !!};
                ">
                    <p class="voucher-item-desc-left">Aktif Hingga: {!! formatTanggal($value['exp_date']) !!}</p>
                    <p class="voucher-item-desc-right">Batas Transaksi: {!! number_format($value['limit'], 0, ',', '.') !!} Transaksi</p>
                    <div style="clear: both;"></div>

                    <p class="voucher-item-code">{!! $value['voucher_number'] !!}</p>

                    <p class="voucher-item-desc-left">Potongan: {!! $value['type'] == 'amount' ? 'Rp. ' . number_format($value['discount'], 0, ',', '.') : $value['discount'] . '%' !!}</p>
                    <p class="voucher-item-desc-right">Maksimal Potongan: Rp. {!! number_format($value['max_discount'], 0, ',', '.') !!}</p>
                    <div style="clear: both;"></div>
                </div>

                <h1 style="font-size: 16px; font-weight: bold; text-decoration: underline; margin-top: 20px;">Syarat & Ketentuan</h1>

                {{-- <ol class="list-tos">
                    <li style="text-align: justify;">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</li>
                    <li style="text-align: justify; margin-top: 10px;">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</li>
                    <li style="text-align: justify; margin-top: 10px;">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</li>
                </ol> --}}
                @if(count($membershipTerms) > 0)
                        {!! $membershipTerms[0] !!}
                    <script>
                        document.getElementsByTagName("ol").classList.add("list-tos");;
                    </script>
                @endif
            @endforeach
        @endif
    </div>    
</body>
</html>