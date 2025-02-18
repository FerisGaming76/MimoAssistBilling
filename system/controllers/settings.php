<?php

/**
 *  PHP Mikrotik Billing (https://github.com/MimoAssistBilling/)
 *  by https://t.me/mimoassist
 **/
_admin();
$ui->assign('_title', Lang::T('Settings'));
$ui->assign('_system_menu', 'settings');

$action = $routes['1'];
$ui->assign('_admin', $admin);

switch ($action) {
    case 'app':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        if (!empty(_get('testWa'))) {
            $result = Message::sendWhatsapp(_get('testWa'), 'MimoAssist Test Whatsapp');
            r2(U . "settings/app", 's', 'Test Whatsapp has been send<br>Result: ' . $result);
        }
        if (!empty(_get('testSms'))) {
            $result = Message::sendSMS(_get('testSms'), 'MimoAssist Test SMS');
            r2(U . "settings/app", 's', 'Test SMS has been send<br>Result: ' . $result);
        }
        if (!empty(_get('testTg'))) {
            $result = Message::sendTelegram('MimoAssist Test Telegram');
            r2(U . "settings/app", 's', 'Test Telegram has been send<br>Result: ' . $result);
        }

        $UPLOAD_URL_PATH = str_replace($root_path,'',  $UPLOAD_PATH);
        if (file_exists($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo.png')) {
            $logo = $UPLOAD_URL_PATH . DIRECTORY_SEPARATOR . 'logo.png?' . time();
        } else {
            $logo = $UPLOAD_URL_PATH . DIRECTORY_SEPARATOR . 'logo.default.png';
        }
        $ui->assign('logo', $logo);
        if ($config['radius_enable'] && empty($config['radius_client'])) {
            try {
                $config['radius_client'] = Radius::getClient();
                $ui->assign('_c', $_c);
            } catch (Exception $e) {
                //ignore
            }
        }
        $themes = [];
        $files = scandir('ui/themes/');
        foreach ($files as $file) {
            if (is_dir('ui/themes/' . $file) && !in_array($file, ['.', '..'])) {
                $themes[] = $file;
            }
        }
        $r = ORM::for_table('tbl_routers')->find_many();
        $ui->assign('r', $r);
        if (function_exists("shell_exec")) {
            $php = trim(shell_exec('which php'));
            if (empty($php)) {
                $php = 'php';
            }
        } else {
            $php = 'php';
        }
        if (empty($config['api_key'])) {
            $config['api_key'] = sha1(uniqid(rand(), true));
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'api_key')->find_one();
            if ($d) {
                $d->value = $config['api_key'];
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'api_key';
                $d->value = $config['api_key'];
                $d->save();
            }
        }
        $ui->assign('_c', $config);
        $ui->assign('php', $php);
        $ui->assign('dir', str_replace('controllers', '', __DIR__));
        $ui->assign('themes', $themes);
        run_hook('view_app_settings'); #HOOK
        $ui->display('app-settings.tpl');
        break;

    case 'app-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $company = _post('CompanyName');
        run_hook('save_settings'); #HOOK


        if (!empty($_FILES['logo']['name'])) {
            if (function_exists('imagecreatetruecolor')) {
                if (file_exists($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo.png')) unlink($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo.png');
                File::resizeCropImage($_FILES['logo']['tmp_name'], $UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo.png', 1078, 200, 100);
                if (file_exists($_FILES['logo']['tmp_name'])) unlink($_FILES['logo']['tmp_name']);
            } else {
                r2(U . 'settings/app', 'e', 'PHP GD is not installed');
            }
        }
        if ($company == '') {
            r2(U . 'settings/app', 'e', Lang::T('All field is required'));
        } else {
            if ($radius_enable) {
                try {
                    Radius::getTableNas()->find_many();
                } catch (Exception $e) {
                    $ui->assign("error_title", "RADIUS Error");
                    $ui->assign("error_message", "Radius table not found.<br><br>" .
                        $e->getMessage() .
                        "<br><br>Download <a href=\"https://raw.githubusercontent.com/hotspotbilling/MimoAssist/Development/install/radius.sql\">here</a> or <a href=\"https://raw.githubusercontent.com/hotspotbilling/MimoAssist/master/install/radius.sql\">here</a> and import it to database.<br><br>Check config.php for radius connection details");
                    $ui->display('router-error.tpl');
                    die();
                }
            }
            // save all settings
            foreach ($_POST as $key => $value) {
                $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
                if ($d) {
                    $d->value = $value;
                    $d->save();
                } else {
                    $d = ORM::for_table('tbl_appconfig')->create();
                    $d->setting = $key;
                    $d->value = $value;
                    $d->save();
                }
            }

            //checkbox
            $checks = ['hide_mrc', 'hide_tms', 'hide_aui', 'hide_al', 'hide_uet', 'hide_vs', 'hide_pg'];
            foreach ($checks as $check) {
                if (!isset($_POST[$check])) {
                    $d = ORM::for_table('tbl_appconfig')->where('setting', $check)->find_one();
                    if ($d) {
                        $d->value = 'no';
                        $d->save();
                    } else {
                        $d = ORM::for_table('tbl_appconfig')->create();
                        $d->setting = $check;
                        $d->value = 'no';
                        $d->save();
                    }
                }
            }

            _log('[' . $admin['username'] . ']: ' . Lang::T('Settings Saved Successfully'), $admin['user_type'], $admin['id']);

            r2(U . 'settings/app', 's', Lang::T('Settings Saved Successfully'));
        }
        break;

    case 'localisation':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $folders = [];
        $files = scandir('system/lan/');
        foreach ($files as $file) {
            if (is_file('system/lan/' . $file) && !in_array($file, ['index.html', 'country.json', '.DS_Store'])) {
                $file = str_replace(".json", "", $file);
                $folders[$file] = '';
            }
        }
        $ui->assign('lani', $folders);
        $lans = Lang::getIsoLang();
        foreach ($lans as $lan => $val) {
            if (isset($folders[$lan])) {
                unset($lans[$lan]);
            }
        }
        $ui->assign('lan', $lans);
        $timezonelist = Timezone::timezoneList();
        $ui->assign('tlist', $timezonelist);
        $ui->assign('xjq', ' $("#tzone").select2(); ');
        run_hook('view_localisation'); #HOOK
        $ui->display('app-localisation.tpl');
        break;

    case 'localisation-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $tzone = _post('tzone');
        $date_format = _post('date_format');
        $country_code_phone = _post('country_code_phone');
        $lan = _post('lan');
        run_hook('save_localisation'); #HOOK
        if ($tzone == '' or $date_format == '' or $lan == '') {
            r2(U . 'settings/app', 'e', Lang::T('All field is required'));
        } else {
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'timezone')->find_one();
            $d->value = $tzone;
            $d->save();

            $d = ORM::for_table('tbl_appconfig')->where('setting', 'date_format')->find_one();
            $d->value = $date_format;
            $d->save();

            $dec_point = $_POST['dec_point'];
            if (strlen($dec_point) == '1') {
                $d = ORM::for_table('tbl_appconfig')->where('setting', 'dec_point')->find_one();
                $d->value = $dec_point;
                $d->save();
            }

            $thousands_sep = $_POST['thousands_sep'];
            if (strlen($thousands_sep) == '1') {
                $d = ORM::for_table('tbl_appconfig')->where('setting', 'thousands_sep')->find_one();
                $d->value = $thousands_sep;
                $d->save();
            }

            $d = ORM::for_table('tbl_appconfig')->where('setting', 'country_code_phone')->find_one();
            if ($d) {
                $d->value = $country_code_phone;
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'country_code_phone';
                $d->value = $country_code_phone;
                $d->save();
            }

            $d = ORM::for_table('tbl_appconfig')->where('setting', 'radius_plan')->find_one();
            if ($d) {
                $d->value = _post('radius_plan');
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'radius_plan';
                $d->value = _post('radius_plan');
                $d->save();
            }
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'hotspot_plan')->find_one();
            if ($d) {
                $d->value = _post('hotspot_plan');
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'hotspot_plan';
                $d->value = _post('hotspot_plan');
                $d->save();
            }
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'pppoe_plan')->find_one();
            if ($d) {
                $d->value = _post('pppoe_plan');
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'pppoe_plan';
                $d->value = _post('pppoe_plan');
                $d->save();
            }

            $currency_code = $_POST['currency_code'];
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'currency_code')->find_one();
            $d->value = $currency_code;
            $d->save();

            $d = ORM::for_table('tbl_appconfig')->where('setting', 'language')->find_one();
            $d->value = $lan;
            $d->save();
            unset($_SESSION['Lang']);
            _log('[' . $admin['username'] . ']: ' . Lang::T('Settings Saved Successfully'), $admin['user_type'], $admin['id']);
            r2(U . 'settings/localisation', 's', Lang::T('Settings Saved Successfully'));
        }
        break;

    case 'users':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $search = _req('search');
        if ($search != '') {
            if ($admin['user_type'] == 'SuperAdmin') {
                $paginator = Paginator::build(ORM::for_table('tbl_users'), ['username' => '%' . $search . '%'], $search);
                $d = ORM::for_table('tbl_users')
                    ->where_like('username', '%' . $search . '%')
                    ->offset($paginator['startpoint'])
                    ->limit($paginator['limit'])->order_by_asc('id')->findArray();
            } else if ($admin['user_type'] == 'Admin') {
                $paginator = Paginator::build(ORM::for_table('tbl_users'), [
                    'username' => '%' . $search . '%',
                    ['user_type' => 'Report'],
                    ['user_type' => 'Agent'],
                    ['user_type' => 'Sales'],
                    ['id' => $admin['id']]
                ], $search);
                $d = ORM::for_table('tbl_users')
                    ->where_like('username', '%' . $search . '%')
                    ->where_any_is([
                        ['user_type' => 'Report'],
                        ['user_type' => 'Agent'],
                        ['user_type' => 'Sales'],
                        ['id' => $admin['id']]
                    ])
                    ->offset($paginator['startpoint'])
                    ->limit($paginator['limit'])->order_by_asc('id')->findArray();
            } else {
                $paginator = Paginator::build(ORM::for_table('tbl_users'), ['username' => '%' . $search . '%'], $search);
                $d = ORM::for_table('tbl_users')
                    ->where_like('username', '%' . $search . '%')
                    ->where_any_is([
                        ['id' => $admin['id']],
                        ['root' => $admin['id']]
                    ])
                    ->offset($paginator['startpoint'])
                    ->limit($paginator['limit'])->order_by_asc('id')->findArray();
            }
        } else {
            if ($admin['user_type'] == 'SuperAdmin') {
                $paginator = Paginator::build(ORM::for_table('tbl_users'));
                $d = ORM::for_table('tbl_users')->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_asc('id')->findArray();
            } else if ($admin['user_type'] == 'Admin') {
                $paginator = Paginator::build(ORM::for_table('tbl_users'));
                $d = ORM::for_table('tbl_users')->where_any_is([
                    ['user_type' => 'Report'],
                    ['user_type' => 'Agent'],
                    ['user_type' => 'Sales'],
                    ['id' => $admin['id']]
                ])->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_asc('id')->findArray();
            } else {
                $paginator = Paginator::build(ORM::for_table('tbl_users'));
                $d = ORM::for_table('tbl_users')
                    ->where_any_is([
                        ['id' => $admin['id']],
                        ['root' => $admin['id']]
                    ])
                    ->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_asc('id')->findArray();
            }
        }
        $admins = [];
        foreach ($d as $k) {
            if (!empty($k['root'])) {
                $admins[] = $k['root'];
            }
        }
        if (count($admins) > 0) {
            $adms = ORM::for_table('tbl_users')->where_in('id', $admins)->findArray();
            unset($admins);
            foreach ($adms as $adm) {
                $admins[$adm['id']] = $adm['fullname'];
            }
        }
        if ($isApi) {
            showResult(true, $action, [
                'admins' => $d,
                'roots' => $admins
            ], ['search' => $search]);
        }
        $ui->assign('admins', $admins);
        $ui->assign('d', $d);
        $ui->assign('search', $search);
        $ui->assign('paginator', $paginator);
        run_hook('view_list_admin'); #HOOK
        $ui->display('users.tpl');
        break;

    case 'users-add':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('_title', Lang::T('Add User'));
        $ui->assign('agents', ORM::for_table('tbl_users')->where('user_type', 'Agent')->find_many());
        $ui->display('users-add.tpl');
        break;
    case 'users-view':
        $ui->assign('_title', Lang::T('Edit User'));
        $id  = $routes['2'];
        if (empty($id)) {
            $id = $admin['id'];
        }
        //allow see himself
        if ($admin['id'] == $id) {
            $d = ORM::for_table('tbl_users')->where('id', $id)->find_array($id)[0];
        } else {
            if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
                // Super Admin can see anyone
                $d = ORM::for_table('tbl_users')->where('id', $id)->find_array()[0];
            } else if ($admin['user_type'] == 'Agent') {
                // Agent can see Sales
                $d = ORM::for_table('tbl_users')->where_any_is([['root' => $admin['id']], ['id' => $id]])->find_array()[0];
            }
        }
        if ($d) {
            run_hook('view_edit_admin'); #HOOK
            if ($d['user_type'] == 'Sales') {
                $ui->assign('agent', ORM::for_table('tbl_users')->where('id', $d['root'])->find_array()[0]);
            }
            if ($isApi) {
                unset($d['password']);
                $agent = $ui->get('agent');
                if ($agent) unset($agent['password']);
                showResult(true, $action, [
                    'admin' => $d,
                    'agent' => $agent
                ], ['search' => $search]);
            }
            $ui->assign('d', $d);
            $ui->assign('_title', $d['username']);
            $ui->display('users-view.tpl');
        } else {
            r2(U . 'settings/users', 'e', $_L['Account_Not_Found']);
        }
        break;
    case 'users-edit':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('_title', Lang::T('Edit User'));
        $id  = $routes['2'];
        if (empty($id)) {
            $id = $admin['id'];
        }
        if ($admin['id'] == $id) {
            $d = ORM::for_table('tbl_users')->find_one($id);
        } else {
            if ($admin['user_type'] == 'SuperAdmin') {
                $d = ORM::for_table('tbl_users')->find_one($id);
                $ui->assign('agents', ORM::for_table('tbl_users')->where('user_type', 'Agent')->find_many());
            } else if ($admin['user_type'] == 'Admin') {
                $d = ORM::for_table('tbl_users')->where_any_is([
                    ['user_type' => 'Report'],
                    ['user_type' => 'Agent'],
                    ['user_type' => 'Sales']
                ])->find_one($id);
                $ui->assign('agents', ORM::for_table('tbl_users')->where('user_type', 'Agent')->find_many());
            } else {
                // Agent cannot move Sales to other Agent
                $ui->assign('agents', ORM::for_table('tbl_users')->where('id', $admin['id'])->find_many());
                $d = ORM::for_table('tbl_users')->where('root', $admin['id'])->find_one($id);
            }
        }
        if ($d) {
            $ui->assign('id', $id);
            $ui->assign('d', $d);
            run_hook('view_edit_admin'); #HOOK
            $ui->display('users-edit.tpl');
        } else {
            r2(U . 'settings/users', 'e', $_L['Account_Not_Found']);
        }
        break;

    case 'users-delete':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        $id  = $routes['2'];
        if (($admin['id']) == $id) {
            r2(U . 'settings/users', 'e', 'Sorry You can\'t delete yourself');
        }
        $d = ORM::for_table('tbl_users')->find_one($id);
        if ($d) {
            run_hook('delete_admin'); #HOOK
            $d->delete();
            r2(U . 'settings/users', 's', Lang::T('User deleted Successfully'));
        } else {
            r2(U . 'settings/users', 'e', $_L['Account_Not_Found']);
        }
        break;

    case 'users-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $username = _post('username');
        $fullname = _post('fullname');
        $password = _post('password');
        $user_type = _post('user_type');
        $phone = _post('phone');
        $email = _post('email');
        $city = _post('city');
        $subdistrict = _post('subdistrict');
        $ward = _post('ward');
        $send_notif = _post('send_notif');
        $root = _post('root');
        $msg = '';
        if (Validator::Length($username, 45, 2) == false) {
            $msg .= Lang::T('Username should be between 3 to 45 characters') . '<br>';
        }
        if (Validator::Length($fullname, 45, 2) == false) {
            $msg .= Lang::T('Full Name should be between 3 to 45 characters') . '<br>';
        }
        if (!Validator::Length($password, 1000, 5)) {
            $msg .= Lang::T('Password should be minimum 6 characters') . '<br>';
        }

        $d = ORM::for_table('tbl_users')->where('username', $username)->find_one();
        if ($d) {
            $msg .= Lang::T('Account already axist') . '<br>';
        }
        $date_now = date("Y-m-d H:i:s");
        run_hook('add_admin'); #HOOK
        if ($msg == '') {
            $passwordC = Password::_crypt($password);
            $d = ORM::for_table('tbl_users')->create();
            $d->username = $username;
            $d->fullname = $fullname;
            $d->password = $passwordC;
            $d->user_type = $user_type;
            $d->phone = $phone;
            $d->email = $email;
            $d->city = $city;
            $d->subdistrict = $subdistrict;
            $d->ward = $ward;
            $d->status = 'Active';
            $d->creationdate = $date_now;
            if ($admin['user_type'] == 'Agent') {
                // Prevent hacking from form
                $d->root = $admin['id'];
            } else if ($user_type == 'Sales') {
                $d->root = $root;
            }
            $d->save();

            if ($send_notif == 'wa') {
                Message::sendWhatsapp(Lang::phoneFormat($phone), Lang::T('Hello, Your account has been created successfully.') . "\nUsername: $username\nPassword: $password\n\n" . $config['CompanyName']);
            } else if ($send_notif == 'sms') {
                Message::sendSMS($phone, Lang::T('Hello, Your account has been created successfully.') . "\nUsername: $username\nPassword: $password\n\n" . $config['CompanyName']);
            }

            _log('[' . $admin['username'] . ']: ' . "Created $user_type <b>$username</b>", $admin['user_type'], $admin['id']);
            r2(U . 'settings/users', 's', Lang::T('Account Created Successfully'));
        } else {
            r2(U . 'settings/users-add', 'e', $msg);
        }
        break;

    case 'users-edit-post':
        $username = _post('username');
        $fullname = _post('fullname');
        $password = _post('password');
        $cpassword = _post('cpassword');
        $user_type = _post('user_type');
        $phone = _post('phone');
        $email = _post('email');
        $city = _post('city');
        $subdistrict = _post('subdistrict');
        $ward = _post('ward');
        $status = _post('status');
        $root = _post('root');
        $msg = '';
        if (Validator::Length($username, 45, 2) == false) {
            $msg .= Lang::T('Username should be between 3 to 45 characters') . '<br>';
        }
        if (Validator::Length($fullname, 45, 2) == false) {
            $msg .= Lang::T('Full Name should be between 3 to 45 characters') . '<br>';
        }
        if ($password != '') {
            if (!Validator::Length($password, 1000, 5)) {
                $msg .= Lang::T('Password should be minimum 6 characters') . '<br>';
            }
            if ($password != $cpassword) {
                $msg .= Lang::T('Passwords does not match') . '<br>';
            }
        }

        $id = _post('id');
        if ($admin['id'] == $id) {
            $d = ORM::for_table('tbl_users')->find_one($id);
        } else {
            if ($admin['user_type'] == 'SuperAdmin') {
                $d = ORM::for_table('tbl_users')->find_one($id);
            } else if ($admin['user_type'] == 'Admin') {
                $d = ORM::for_table('tbl_users')->where_any_is([
                    ['user_type' => 'Report'],
                    ['user_type' => 'Agent'],
                    ['user_type' => 'Sales']
                ])->find_one($id);
            } else {
                $d = ORM::for_table('tbl_users')->where('root', $admin['id'])->find_one($id);
            }
        }
        if (!$d) {
            $msg .= Lang::T('Data Not Found') . '<br>';
        }

        if ($d['username'] != $username) {
            $c = ORM::for_table('tbl_users')->where('username', $username)->find_one();
            if ($c) {
                $msg .= "<b>$username</b> " . Lang::T('Account already axist') . '<br>';
            }
        }
        run_hook('edit_admin'); #HOOK
        if ($msg == '') {
            $d->username = $username;
            if ($password != '') {
                $password = Password::_crypt($password);
                $d->password = $password;
            }

            $d->fullname = $fullname;
            if (($admin['id']) != $id) {
                $user_type = _post('user_type');
                $d->user_type = $user_type;
            }
            $d->phone = $phone;
            $d->email = $email;
            $d->city = $city;
            $d->subdistrict = $subdistrict;
            $d->ward = $ward;
            if (isset($_POST['status'])) {
                $d->status = $status;
            }

            if ($admin['user_type'] == 'Agent') {
                // Prevent hacking from form
                $d->root = $admin['id'];
            } else if ($user_type == 'Sales') {
                $d->root = $root;
            }

            $d->save();

            _log('[' . $admin['username'] . ']: $username ' . Lang::T('User Updated Successfully'), $admin['user_type'], $admin['id']);
            r2(U . 'settings/users', 's', 'User Updated Successfully');
        } else {
            r2(U . 'settings/users-edit/' . $id, 'e', $msg);
        }
        break;

    case 'change-password':
        run_hook('view_change_password'); #HOOK
        $ui->display('change-password.tpl');
        break;

    case 'change-password-post':
        $password = _post('password');
        if ($password != '') {
            $d = ORM::for_table('tbl_users')->where('username', $admin['username'])->find_one();
            run_hook('change_password'); #HOOK
            if ($d) {
                $d_pass = $d['password'];
                if (Password::_verify($password, $d_pass) == true) {
                    $npass = _post('npass');
                    $cnpass = _post('cnpass');
                    if (!Validator::Length($npass, 15, 5)) {
                        r2(U . 'settings/change-password', 'e', 'New Password must be 6 to 14 character');
                    }
                    if ($npass != $cnpass) {
                        r2(U . 'settings/change-password', 'e', 'Both Password should be same');
                    }

                    $npass = Password::_crypt($npass);
                    $d->password = $npass;
                    $d->save();

                    _msglog('s', Lang::T('Password changed successfully, Please login again'));
                    _log('[' . $admin['username'] . ']: Password changed successfully', $admin['user_type'], $admin['id']);

                    r2(U . 'admin');
                } else {
                    r2(U . 'settings/change-password', 'e', Lang::T('Incorrect Current Password'));
                }
            } else {
                r2(U . 'settings/change-password', 'e', Lang::T('Incorrect Current Password'));
            }
        } else {
            r2(U . 'settings/change-password', 'e', Lang::T('Incorrect Current Password'));
        }
        break;

    case 'notifications':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        run_hook('view_notifications'); #HOOK
        if (file_exists($UPLOAD_PATH . DIRECTORY_SEPARATOR . "notifications.json")) {
            $ui->assign('_json', json_decode(file_get_contents($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'notifications.json'), true));
        } else {
            $ui->assign('_json', json_decode(file_get_contents($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'notifications.default.json'), true));
        }
        $ui->assign('_default', json_decode(file_get_contents($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'notifications.default.json'), true));
        $ui->display('app-notifications.tpl');
        break;
    case 'notifications-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        file_put_contents($UPLOAD_PATH . "/notifications.json", json_encode($_POST));
        r2(U . 'settings/notifications', 's', Lang::T('Settings Saved Successfully'));
        break;
    case 'dbstatus':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        $dbc = new mysqli($db_host, $db_user, $db_password, $db_name);
        if ($result = $dbc->query('SHOW TABLE STATUS')) {
            $tables = array();
            while ($row = $result->fetch_array()) {
                $tables[$row['Name']]['rows'] = ORM::for_table($row["Name"])->count();
                $tables[$row['Name']]['name'] = $row["Name"];
            }
            $ui->assign('tables', $tables);
            run_hook('view_database'); #HOOK
            $ui->display('dbstatus.tpl');
        }
        break;

    case 'dbbackup':
        if (!in_array($admin['user_type'], ['SuperAdmin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $tables = $_POST['tables'];
        set_time_limit(-1);
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Disposition: attachment;filename="MimoAssist_' . count($tables) . '_tables_' . date('Y-m-d_H_i') . '.json"');
        header('Content-Transfer-Encoding: binary');
        $array = [];
        foreach ($tables as $table) {
            $array[$table] = ORM::for_table($table)->find_array();
        }
        echo json_encode($array);
        break;
    case 'dbrestore':
        if (!in_array($admin['user_type'], ['SuperAdmin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        if (file_exists($_FILES['json']['tmp_name'])) {
            $suc = 0;
            $fal = 0;
            $json = json_decode(file_get_contents($_FILES['json']['tmp_name']), true);
            foreach ($json as $table => $records) {
                ORM::raw_execute("TRUNCATE $table;");
                foreach ($records as $rec) {
                    $t = ORM::for_table($table)->create();
                    foreach ($rec as $k => $v) {
                        if ($k != 'id') {
                            $t->set($k, $v);
                        }
                    }
                    if ($t->save()) {
                        $suc++;
                    } else {
                        $fal++;
                    }
                }
            }
            if (file_exists($_FILES['json']['tmp_name'])) unlink($_FILES['json']['tmp_name']);
            r2(U . "settings/dbstatus", 's', "Restored $suc success $fal failed");
        } else {
            r2(U . "settings/dbstatus", 'e', 'Upload failed');
        }
        break;
    case 'language':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        run_hook('view_add_language'); #HOOK
        if (file_exists($lan_file)) {
            $ui->assign('langs', json_decode(file_get_contents($lan_file), true));
        } else {
            $ui->assign('langs', []);
        }
        $ui->display('language-add.tpl');
        break;

    case 'lang-post':
        file_put_contents($lan_file, json_encode($_POST, JSON_PRETTY_PRINT));
        r2(U . 'settings/language', 's', Lang::T('Translation saved Successfully'));
        break;

    default:
        $ui->display('a404.tpl');
}
