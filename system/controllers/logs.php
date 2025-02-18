<?php

/**
 *  PHP Mikrotik Billing (https://github.com/MimoAssistBilling/)
 *  by https://t.me/mimoassist
 **/

_admin();
$ui->assign('_title', 'MimoAssist Logs');
$ui->assign('_system_menu', 'logs');

$action = $routes['1'];
$ui->assign('_admin', $admin);

if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
    _alert(Lang::T('You do not have permission to access this page'),'danger', "dashboard");
}


switch ($action) {
    case 'list':
        $q = (_post('q') ? _post('q') : _get('q'));
        $keep = _post('keep');
        if (!empty($keep)) {
            ORM::raw_execute("DELETE FROM tbl_logs WHERE UNIX_TIMESTAMP(date) < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $keep DAY))");
            r2(U . "logs/list/", 's', "Delete logs older than $keep days");
        }
        if ($q != '') {
            $paginator = Paginator::build(ORM::for_table('tbl_logs'), ['description' => '%' . $q . '%'], $q);
            $d = ORM::for_table('tbl_logs')->where_like('description', '%' . $q . '%')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        } else {
            $paginator = Paginator::build(ORM::for_table('tbl_logs'));
            $d = ORM::for_table('tbl_logs')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        }

        $ui->assign('d', $d);
        $ui->assign('q', $q);
        $ui->assign('paginator', $paginator);
        $ui->display('logs.tpl');
        break;
    case 'radius':
        $q = (_post('q') ? _post('q') : _get('q'));
        $keep = _post('keep');
        if (!empty($keep)) {
            ORM::raw_execute("DELETE FROM radpostauth WHERE UNIX_TIMESTAMP(authdate) < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $keep DAY))", [], 'radius');
            r2(U . "logs/radius/", 's', "Delete logs older than $keep days");
        }
        if ($q != '') {
            $paginator = Paginator::build(ORM::for_table('radpostauth', 'radius'), ['username' => '%' . $q . '%'], $q);
            $d = ORM::for_table('radpostauth', 'radius')->where_like('username', '%' . $q . '%')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        } else {
            $paginator = Paginator::build(ORM::for_table('radpostauth', 'radius'));
            $d = ORM::for_table('radpostauth', 'radius')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        }

        $ui->assign('d', $d);
        $ui->assign('q', $q);
        $ui->assign('paginator', $paginator);
        $ui->display('logs-radius.tpl');
        break;


    default:
        r2(U . 'logs/list/', 's', '');
}
