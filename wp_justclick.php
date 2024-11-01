<?php
/*
Plugin Name: WP Justclick
Plugin URI: http://setwork.ru
Description: Перенос в подписчики Justclick зарегистрированных пользователей Wordpress
Version: 1.0.1
Author: Davletyants Artem
Author URI: http://setwork.ru
*/

/*  Copyright 2013  Davletyants Artem  (email : artem {at} setwork.ru)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function wpjc_justclick_install(){

}
register_activation_hook(__FILE__,'wpjc_justclick_install');

function wpjc_justclick_uninstall() {

}
register_uninstall_hook(__FILE__, 'wpjc_justclick_uninstall');

add_action('admin_menu', 'wpjc_justclick_options_panel');

function wpjc_justclick_options_panel(){
	add_options_page('Настройки WP Justclick', 'WP Justclick', 'manage_options', 'manage-wpjc-justclick', 'wpjc_justclick_options');
}

function wpjc_justclick_options(){
?>
 <div class="wrap">
  <h2>Настройки WP Justclick</h2>
  <form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
	<table cellpadding="5">
	      <tr>
		  <td><strong>Ваш логин в Justclick:</strong></td>
		  <td><input type="text" name="login_wpjc_justclick" value="<?php echo get_option('login_wpjc_justclick'); ?>"></td>
	      </tr>
	      <tr>
		  <td><strong>Секретный ключ для подписи:</strong></td>
		  <td><input type="text" name="secretkey_wpjc_justclick" value="<?php echo get_option('secretkey_wpjc_justclick'); ?>"></td>
   	      </tr>
	      <tr>
		  <td><strong>Группа Justclick:</strong></td>
		  <td><input type="text" name="group_wpjc_justclick" value="<?php echo get_option('group_wpjc_justclick'); ?>"></td>
	      </tr>
              <tr>
		  <td><strong>Метка:</strong></td>
		  <td><input type="text" name="marker_wpjc_justclick" value="<?php echo get_option('marker_wpjc_justclick'); ?>"></td>
	      </tr>
	</table>
        <p></p>

        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Сохранить изменения"></p>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="login_wpjc_justclick, secretkey_wpjc_justclick, group_wpjc_justclick, marker_wpjc_justclick" />
  </form>
 </div>
<?php
}
function wpjc_add_new_user_in_group_justclick($user_id){

 	// Логин в системе Justclick
	$user_rs['user_id'] = get_option('login_wpjc_justclick');

	// Ключ для формирования подписи. см. "Магазин" - "Настройки" - "RussianPostService и API" - "Секретный ключ для подписи"
	$user_rs['user_rps_key'] = get_option('secretkey_wpjc_justclick');

	// Формируем массив данных для передачи в API
	$user = get_userdata($user_id);
	$send_data = array(
	'rid[0]' => get_option('group_wpjc_justclick'),
	'lead_name' => $user->display_name,
	'lead_email' => $user->user_email,
	'lead_phone' => '',
	'lead_city' => '',
	'aff' => '',
	'tag' => get_option('marker_wpjc_justclick'),
	'ad' => '',
	'doneurl2' => '',
	 );

	// Формируем подпись к передаваемым данным
	$send_data['hash'] = GetHash($send_data, $user_rs);

	// Вызываем функцию AddLeadToGroup в API и декодируем полученные данные
	$resp = json_decode(Send('http://'.$user_rs['user_id'].'.justclick.ru/api/AddLeadToGroup', $send_data));
}
add_action('user_register','wpjc_add_new_user_in_group_justclick');

// =========== Функции отправки, получения и обработки ответа ============

// Отправляем запрос в API сервиса
function Send($url, $data)
{
 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 curl_setopt($ch, CURLOPT_POST, true);
 curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // выводим ответ в переменную
 $res = curl_exec($ch);
 curl_close($ch);
 return $res;
}

// Формируем подпись к передаваемым в API данным
function GetHash($params, $user_rs) {
 $params = http_build_query($params);
 $user_id = $user_rs['user_id'];
 $secret = $user_rs['user_rps_key'];
 $params = "{$params}::{$user_id}::{$secret}";
 return md5($params);
}

// Проверяем полученную подпись к ответу
function CheckHash($resp, $user_rs) {
 $secret = $user_rs['user_rps_key'];
 $code = $resp->error_code;
 $text = $resp->error_text;
 $hash = md5("$code::$text::$secret");
 if($hash == $resp->hash)
  return true; // подпись верна
 else
  return false; // подпись не верна
}

?>
