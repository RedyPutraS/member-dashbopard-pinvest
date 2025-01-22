<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MembershipActivation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membership:activation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Membership Activation Running..');
        $users = User::whereNotNull('membership_plan_id')->get();

        foreach($users as $user) {
            $isExp = strtotime(date('Y-m-d')) >= strtotime($user->membership_exp);
            if($isExp) {
                $user->membership_plan_id = null;
                $user->membership_start = null;
                $user->membership_exp = null;
                $user->save();

                $userName = $user->first_name . ' ' . $user->last_name;
                Log::info($userName . ' telah expired membership.');
            }
        }

        return Command::SUCCESS;
    }
}
