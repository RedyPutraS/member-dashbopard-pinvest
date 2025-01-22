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
        padding: 10px;
        text-align: center;
        margin: auto;
    ">  
        <div style="
            display: inline-block;
            border-radius: inherit;
            border: 1px solid #595959;
            padding: 15px 10px;
            text-align: center;
            vertical-align: middle;
            padding: 15px;
            width: 85%;
        ">
            <h1 style="
                font-size: 20px; 
                font-weight: 800;
                margin: 0;
            ">
                Selamat Datang di Pinvest!
            </h1>

            <p style="font-size: 14px; color: #8C8C8C;">
                Anda telah berhasil melakukan proses registrasi akun, harap segera verifikasi akun anda dengan klik tombol dibawah ini.
            </p>

            <a target="_blank" href="{!! $verifLink !!}" class="btn-verifikasi">Verifikasi</a>

            <div>
                <p style="
                    font-weight: 600;
                    background-color: #BFBFBF;
                    color: #1F1F1F;
                    border-top-left-radius: 8px;
                    border-top-right-radius: 8px;
                    font-size: 14px;
                    padding: 4px 0;
                    margin-block-end: 0;
                ">Nama Lengkap</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 14px;
                    margin-block-start: 0;
                    margin-block-end: 0;
                    padding: 4px 0;
                ">{!! $user['first_name'] . ' ' . $user['last_name'] !!}</p>
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
                    margin-block-end: 0;
                ">Gender</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 14px;
                    margin-block-start: 0;
                    margin-block-end: 0;
                    padding: 4px 0;
                ">{!! $user['gender'] !!}</p>
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
                    margin-block-end: 0;
                ">Tanggal Lahir</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 14px;
                    margin-block-start: 0;
                    margin-block-end: 0;
                    padding: 4px 0;
                ">{!! $user['birth_date'] !!}</p>
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
                    margin-block-end: 0;
                ">No Telpon</p>
                <p style="
                    background-color: #FAFAFA;
                    color: #1F1F1F;
                    font-size: 14px;
                    margin-block-start: 0;
                    margin-block-end: 0;
                    padding: 4px 0;
                ">{!! $user['no_hp'] !!}</p>
            </div>
        </div>
    </div>    
</body>
</html>