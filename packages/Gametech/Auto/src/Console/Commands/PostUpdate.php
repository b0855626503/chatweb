<?php

namespace Gametech\Auto\Console\Commands;

use Gametech\Admin\Contracts\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PostUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postupdate:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto Topup From Payment To Member';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('migrate', [
            '--force' => true
        ]);

        $this->call('optimize:clear');

        $array = ["dashboard","bank_in","bank_in.update","bank_in.clear","bank_in.delete","bank_out","bank_out.clear","bank_out.delete","withdraw","withdraw.edit","withdraw.clear","withdraw.delete","withdraw_free","withdraw_free.edit","withdraw_free.clear","withdraw_free.delete","confirm_wallet","confirm_wallet.edit","confirm_wallet.clear","confirm_wallet.delete","payment","payment.create","payment.update","payment.delete","wallet","wallet.member","wallet.member.refill","wallet.member.setwallet","wallet.member.setpoint","wallet.member.setdiamond","wallet.member.update","wallet.member.delete","wallet.member.tel","wallet.rp_wallet","wallet.rp_bill","wallet.rp_deposit","wallet.rp_withdraw","wallet.rp_setpoint","wallet.rp_setdiamond","credit","credit.member_free","credit.member_free.setwallet","credit.rp_credit","credit.rp_bill_free","credit.rp_withdraw_free","mop","mop.rp_reward_point","mop.rp_cashback","mop.rp_member_ic","mop.rp_top_promotion","mep","mep.rp_billturn","mep.rp_spin","mep.rp_sponsor","mep.rp_online_behavior","mep.rp_user_log","mon","mon.rp_alllog","mon.rp_sum_game","mon.rp_sum_stat","mon.rp_sum_payment","ats","ats.bank_account_in","ats.bank_account_in.create","ats.bank_account_in.update","ats.bank_account_in.delete","ats.bank_account_out","ats.bank_account_out.create","ats.bank_account_out.update","ats.bank_account_out.delete","top","top.game","top.game.update","top.batch_user","top.batch_user.create","top.promotion","top.promotion.update","top.pro_content","top.pro_content.create","top.pro_content.update","top.pro_content.delete","st","st.setting","st.setting.update","st.faq","st.faq.create","st.faq.update","st.faq.delete","st.refer","st.refer.update","st.bank","st.bank.update","st.bank_rule","st.bank_rule.create","st.bank_rule.update","st.bank_rule.delete","st.spin","st.spin.update","st.reward","st.reward.create","st.reward.update","st.reward.delete","dev","dev.employees","dev.employees.create","dev.employees.update","dev.employees.delete","dev.roles","dev.roles.create","dev.roles.update","dev.roles.delete","dev.rp_staff_log"];

        $role = Role::find(2);
        $role->permissions = json_encode($array);
        $role->save();

    }
}
