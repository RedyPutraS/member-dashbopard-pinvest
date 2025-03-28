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

        .container { width: 350px; }

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

        @media only screen and (max-width: 480px) {     
            .container {
                width: 290px;
            }
        }
    </style>
</head>
<body>
    <div class="container" style="
    border: 2px solid #595959;
    border-radius: 8px;
    padding: 15px;
    margin: auto;
    text-align: center;
">  
    <h1 style="
        font-size: 20px; 
        font-weight: 800;
        margin: 0;
    ">
        Atur Ulang Kata Sandi Anda
    </h1>

    <div style="text-align: justify;">
        <div style="margin: auto; width: 200px;"> <!-- Menambahkan style untuk konten gambar -->
            <img src="{{asset('/assets/img/pinvest.png')}}" width="240px" height="200px" style="display: block; margin: 0 auto;"> <!-- Menambahkan style untuk gambar -->
        </div>
        <p style="font-size: 14px; color: #8C8C8C;">
            Anda telah mengirimkan permintaan untuk mengatur ulang Kata Sandi akun anda, silahkan klik tombol dibawah ini untuk menuju halaman atur Kata Sandi. 
        </p>

        <p style="font-size: 14px; color: #8C8C8C;">
            Tombol dibawah hanya dapat anda gunakan sekali, dan hanya aktif dalam 1 Jam kedepan (terhitung dari email ini terkirim).
        </p>
    </div>

    <a target="_blank" href="{!! $resetLink !!}" class="btn-verifikasi">Ubah Kata Sandi</a>
</div>  
</body>
</html>