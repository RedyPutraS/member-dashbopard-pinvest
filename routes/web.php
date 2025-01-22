<?php

use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\ProfileController;
use App\Mail\NotifTransaksiMail;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

function generateQr() {
    $number = rand(1, 100000);
    $micro_date = microtime();
    $date_array = explode(" ", $micro_date);
    $microtime = str_replace(':', '', date('h:i:s')) . str_replace('.', '', $date_array[0]);
    return $microtime . $number;
}

Route::get('/test', function () {
    $transaction_id = '28';
    $transaction = Transaction::find($transaction_id);

    $event = TransactionDetail::select('event.title', 'event.cover_image', 'event.address', 'event.type', 'users.email AS customer_email', DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS customer_name"))
        ->join('detail_event', 'detail_event.id', 'transaction_detail.detail_event_id')
        ->join('event', 'event.id', 'detail_event.event_id')
        ->join('transaction', 'transaction.id', 'transaction_detail.transaction_id')
        ->join('users', 'users.id', 'transaction.user_id')
        ->where('transaction_detail.transaction_id', $transaction_id)
        ->first()->toArray();

    $selectDetail = [
        'transaction_detail.qr_code', 
        'detail_event.url_meeting', 
        'detail_event.date', 
        'detail_event.start_time', 
        'detail_event.end_time', 
        'detail_event.price', 
        'transaction_detail.detail_event_id'
    ];
    $transactionDetail = TransactionDetail::select($selectDetail)
        ->join('detail_event', 'detail_event.id', 'transaction_detail.' . ($transaction->ticket_pass ? 'ticket_pass_id' : 'detail_event_id'))
        ->where('transaction_detail.transaction_id', $transaction_id)
        ->orderBy('detail_event.date', 'ASC')->get()->toArray();

    if($event['type'] == 'offline') {
        $qrCode = generateQr();
        TransactionDetail::where('transaction_id', $transaction_id)->update(['qr_code' => $qrCode]);
    }

    $notifMail = new NotifTransaksiMail($transaction->toArray(), $event, $transactionDetail, isset($qrCode) ? $qrCode : null);
    Mail::to('randika.rosyid2@gmail.com')->send($notifMail);
    
    // return view('welcome');
});


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
