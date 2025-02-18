<?php

/**
 *  PHP Mikrotik Billing (https://github.com/MimoAssistBilling/)
 *  by https://t.me/mimoassist
 **/

_admin();
$ui->assign('_title', Lang::T('Reports'));
$ui->assign('_system_menu', 'reports');

$action = $routes['1'];
$ui->assign('_admin', $admin);

$mdate = date('Y-m-d');
$mtime = date('H:i:s');
$tdate = date('Y-m-d', strtotime('today - 30 days'));
$firs_day_month = date('Y-m-01');
$this_week_start = date('Y-m-d', strtotime('previous sunday'));
$before_30_days = date('Y-m-d', strtotime('today - 30 days'));
$month_n = date('n');

switch ($action) {
    case 'by-date':
    case 'activation':
        $q = (_post('q') ? _post('q') : _get('q'));
        $keep = _post('keep');
        if (!empty($keep)) {
            ORM::raw_execute("DELETE FROM tbl_transactions WHERE date < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $keep DAY))");
            r2(U . "logs/list/", 's', "Delete logs older than $keep days");
        }
        if ($q != '') {
            $paginator = Paginator::build(ORM::for_table('tbl_transactions'), ['invoice' => '%' . $q . '%'], $q);
            $d = ORM::for_table('tbl_transactions')->where_like('invoice', '%' . $q . '%')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        } else {
            $paginator = Paginator::build(ORM::for_table('tbl_transactions'));
            $d = ORM::for_table('tbl_transactions')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        }

        $ui->assign('activation', $d);
        $ui->assign('q', $q);
        $ui->assign('paginator', $paginator);
        $ui->display('reports-activation.tpl');
        break;
    case 'daily-report':
        $paginator = Paginator::build(ORM::for_table('tbl_transactions'), ['recharged_on' => $mdate]);
        $d = ORM::for_table('tbl_transactions')->where('recharged_on', $mdate)->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        $dr = ORM::for_table('tbl_transactions')->where('recharged_on', $mdate)->sum('price');

        $ui->assign('d', $d);
        $ui->assign('dr', $dr);
        $ui->assign('mdate', $mdate);
        $ui->assign('mtime', $mtime);
        $ui->assign('paginator', $paginator);
        run_hook('view_daily_reports'); #HOOK
        $ui->display('reports-daily.tpl');
        break;

    case 'by-period':
        $ui->assign('mdate', $mdate);
        $ui->assign('mtime', $mtime);
        $ui->assign('tdate', $tdate);
        run_hook('view_reports_by_period'); #HOOK
        $ui->display('reports-period.tpl');
        break;

    case 'period-view':
        $fdate = _post('fdate');
        $tdate = _post('tdate');
        $stype = _post('stype');

        $d = ORM::for_table('tbl_transactions');
        if ($stype != '') {
            $d->where('type', $stype);
        }

        $d->where_gte('recharged_on', $fdate);
        $d->where_lte('recharged_on', $tdate);
        $d->order_by_desc('id');
        $x =  $d->find_many();

        $dr = ORM::for_table('tbl_transactions');
        if ($stype != '') {
            $dr->where('type', $stype);
        }

        $dr->where_gte('recharged_on', $fdate);
        $dr->where_lte('recharged_on', $tdate);
        $xy = $dr->sum('price');

        $ui->assign('d', $x);
        $ui->assign('dr', $xy);
        $ui->assign('fdate', $fdate);
        $ui->assign('tdate', $tdate);
        $ui->assign('stype', $stype);
        run_hook('view_reports_period'); #HOOK
        $ui->display('reports-period-view.tpl');
        break;

    default:
        $ui->display('a404.tpl');
}
