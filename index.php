<?php

/*
Script Name: WP Quick Install
Author: Jonathan Buttigieg
Contributors: Julio Potier, Samuel E. Cerezo
Script URI: http://wp-quick-install.com
Version: 0.2
Licence: GPLv3
Last Update: 19 sep 2016
*/

header('Content-Type: text/html; charset=UTF-8');

@set_time_limit(0);

define('WP_API_CORE', 'http://api.wordpress.org/core/version-check/1.7/?locale=');
define('WPQI_CACHE_PATH', 'cache/');
define('WPQI_CACHE_CORE_PATH', WPQI_CACHE_PATH.'core/');
define('WPQI_CACHE_PLUGINS_PATH', WPQI_CACHE_PATH.'plugins/');
define('WPQI_ABSPATH', $_SERVER['DOCUMENT_ROOT']);

require('inc/functions.php');

if (empty($_GET) && end((explode('/' , trim($_SERVER['REQUEST_URI'], '/')))) == 'wp-install') {
	header('Location: index.php');
	die();
}

if (! is_dir(WPQI_CACHE_PATH)) {
	mkdir(WPQI_CACHE_PATH);
}
if (! is_dir(WPQI_CACHE_CORE_PATH)) {
	mkdir(WPQI_CACHE_CORE_PATH);
}
if (! is_dir(WPQI_CACHE_PLUGINS_PATH)) {
	mkdir(WPQI_CACHE_PLUGINS_PATH);
}

$data = array();

function captureCharacter($textChain) {
	$arrData = array();
	for ($position = 0, $textLen = mb_strlen($textChain,'UTF-8'); $position < $textLen; $position++) {
		$arrData[] = mb_substr ($textChain,$position,1,'UTF-8');
	}

	return $arrData;
}

$directory = ! empty($_POST['directory']) ? '../'.$_POST['directory'].'/' : '../';
$path = ! empty($_POST['directory']) ? '/'.$_POST['directory'].'/' : '/';

if (isset($_GET['action'])) {

	switch($_GET['action']) {

		case "check_before_upload" :

			$data = array();

			try {
			   $db = new PDO('mysql:host='.$_POST['dbhost'].';dbname='.$_POST['dbname'], $_POST['uname'], $_POST['pwd']);
			}
			catch (Exception $e) {
				$data['db'] = "error etablishing connection";
			}

			if (file_exists($directory.'wp-config.php')) {
				$data['wp'] = "error directory";
			}

			echo json_encode($data);

			break;

		case "download_wp" :

			$language = 'es_ES';

			$wp = json_decode(file_get_contents(WP_API_CORE.$language))->offers[0];

			if (! file_exists(WPQI_CACHE_CORE_PATH.'wordpress-'.$wp->version.'-'.$language .'.zip')) {
				file_put_contents(WPQI_CACHE_CORE_PATH.'wordpress-'.$wp->version.'-'.$language .'.zip', file_get_contents($wp->download));
			}

			break;

		case "unzip_wp" :

			$language = 'es_ES';

			$wp = json_decode(file_get_contents(WP_API_CORE.$language))->offers[0];

			if (! empty($directory)) {
				if (! is_dir($directory)) {
					mkdir($directory);
				}
				chmod($directory , 0755);
			}

			$zip = new ZipArchive;

			if ($zip->open(WPQI_CACHE_CORE_PATH.'wordpress-'.$wp->version.'-'.$language .'.zip') === true) {

				$zip->extractTo('.');
				$zip->close();

				$files = scandir('wordpress');

				$files = array_diff($files, array('.', '..'));

				foreach ($files as $file) {
					rename( 'wordpress/'.$file, $directory.'/'.$file);
				}

				removeDirectory('wordpress');

				foreach (array('license.txt', 'licencia.txt', 'readme.html', 'wp-content/plugins/hello.php') as $unlink) {

					$unlink = $directory.$unlink;

					if (file_exists($unlink)) {
						unlink($unlink);
					}

				}

				removeDirectory(WPQI_ABSPATH.$path.'wp-content/plugins/akismet');
			}

			break;

			case "wp_config" :

				$config_file = file($directory.'wp-config-sample.php');

				$secret_keys = explode("\n", file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/'));

				foreach ($secret_keys as $k => $v) {
					$secret_keys[$k] = substr($v, 28, 64);
				}

				$key = 0;

				foreach ($config_file as &$line) {

					if ('$table_prefix =' == substr($line, 0, 15)) {
						$line = '$table_prefix = \''.sanit($_POST['prefix'])."';\r\n";
						continue;
					}

					if (! preg_match('/^define\(\s?\'([A-Z_]+)\',([ ]+)/', $line, $match)) {
						continue;
					}

					$constant = $match[1];

					switch ($constant) {
						case 'WP_DEBUG'	   :

							$line .= "\r\n\n "."/** Aumentar el límite de la memoria */"."\r\n";
							$line .= "define('WP_MEMORY_LIMIT', '128M');"."\r\n";

							break;
						case 'DB_NAME'     :
							$line = "define( 'DB_NAME', '".sanit($_POST[ 'dbname' ])."' );\r\n";
							break;
						case 'DB_USER'     :
							$line = "define( 'DB_USER', '".sanit($_POST['uname'])."' );\r\n";
							break;
						case 'DB_PASSWORD' :
							$line = "define( 'DB_PASSWORD', '".sanit($_POST['pwd'])."' );\r\n";
							break;
						case 'DB_HOST'     :
							$line = "define( 'DB_HOST', '".sanit($_POST['dbhost'])."' );\r\n";
							break;
						case 'AUTH_KEY'         :
						case 'SECURE_AUTH_KEY'  :
						case 'LOGGED_IN_KEY'    :
						case 'NONCE_KEY'        :
						case 'AUTH_SALT'        :
						case 'SECURE_AUTH_SALT' :
						case 'LOGGED_IN_SALT'   :
						case 'NONCE_SALT'       :
							$line = "define( '".$constant."', '".$secret_keys[$key++]."' );\r\n";
							break;

						case 'WPLANG' :
							$line = "define( 'WPLANG', '".sanit($_POST['language'])."' );\r\n";
							break;
					}
				}
				unset($line);

				$handle = fopen($directory.'wp-config.php', 'w');
				foreach ($config_file as $line) {
					fwrite($handle, $line);
				}
				fclose($handle);

				chmod($directory.'wp-config.php', 0666);

				break;

			case "install_wp" :

				define('WP_INSTALLING', true);

				require_once($directory.'wp-load.php');

				require_once($directory.'wp-admin/includes/upgrade.php');

				require_once($directory.'wp-includes/wp-db.php');

				wp_install($_POST['weblog_title'], $_POST['user_login'], $_POST['admin_email'], 0, '', $_POST['admin_password']);

				$protocol = ! is_ssl() ? 'http' : 'https';
                $get = basename(dirname(__FILE__)).'/index.php/wp-admin/install.php?action=install_wp';
                $dir = str_replace('../', '', $directory);
                $link = $protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                $url = str_replace($get, $dir, $link);
                $url = trim($url, '/');

				update_option('siteurl', $url);
				update_option('home', $url);
				update_option('permalink_structure', '/%postname%/');
				update_option('timezone_string', 'Europe/Madrid');
				update_option('time_format', 'H:i');

				if ($_POST['default_content'] == '1') {
					wp_delete_post(1, true);
					wp_delete_post(2, true);
				}

				update_option('thumbnail_size_w', 500);
				update_option('thumbnail_size_h', 500);

				update_option('medium_size_w', 1000);
				update_option('medium_size_h', 1000);

				update_option('large_size_w', 2000);
				update_option('large_size_h', 2000);

				update_option('uploads_use_yearmonth_folders', 0);

				break;

			case "install_theme" :

				require_once($directory.'wp-load.php');

				require_once($directory.'wp-admin/includes/upgrade.php');

				if (! file_exists($_POST['theme'].'.zip')) {
					file_put_contents($_POST['theme'].'.zip', fopen('https://github.com/samuelcerezo/'.$_POST['theme'].'/archive/master.zip', 'r'));
				}

				$zip = new ZipArchive;

				if ($zip->open($_POST['theme'].'.zip') === true) {

					$zip->extractTo($directory.'wp-content/themes/');
					$zip->close();

					rename($directory.'wp-content/themes/'.$_POST['theme'].'-master', $directory.'wp-content/themes/'.$_POST['theme_directory']);

					$theme_directory = $directory.'wp-content/themes/'.$_POST['theme_directory'];

					if (file_exists($theme_directory.'/style.css')) {
						$content = file_get_contents($theme_directory.'/style.css');
						file_put_contents($theme_directory.'/style.css', str_replace('{{NAME}}', $_POST['weblog_title'], $content));
					}

					switch_theme($_POST['theme_directory'], $_POST['theme_directory']);

				}

				delete_theme('twentynineteen');
				delete_theme('twentyseventeen');
				delete_theme('twentysixteen');
				delete_theme('twentyfifteen');
				delete_theme('twentyfourteen');
				delete_theme('twentythirteen');
				delete_theme('twentytwelve');
				delete_theme('twentyeleven');
				delete_theme('twentyten');
				delete_theme('__MACOSX');

				break;

			case "install_plugins" :

				if (! empty($_POST['plugins'])) {

					$plugins     = explode(";", $_POST['plugins']);
					$plugins     = array_map('trim' , $plugins);
					$plugins_dir = $directory.'wp-content/plugins/';

					foreach ($plugins as $plugin) {

					    $plugin_repo = file_get_contents("http://api.wordpress.org/plugins/info/1.0/$plugin.json");

					    if ($plugin_repo && $plugin = json_decode($plugin_repo)) {

							$plugin_path = WPQI_CACHE_PLUGINS_PATH.$plugin->slug.'-'.$plugin->version.'.zip';

							if (! file_exists($plugin_path)) {
								if ($download_link = file_get_contents($plugin->download_link)) {
 									file_put_contents($plugin_path, $download_link);
 								}							}

							$zip = new ZipArchive;
							if ($zip->open($plugin_path) === true) {
								$zip->extractTo($plugins_dir);
								$zip->close();
							}
						}
					}
				}

				if ($_POST['plugins_premium'] == 1) {

					$plugins = scandir('plugins');

					$plugins = array_diff($plugins, array('.', '..'));

					foreach ($plugins as $plugin) {
						if (preg_match('#(.*).zip$#', $plugin) == 1) {
							$zip = new ZipArchive;
							if ($zip->open('plugins/'.$plugin) === true) {
								$zip->extractTo($plugins_dir);
								$zip->close();

							}
						}
					}
				}

				require_once($directory.'wp-load.php');
				require_once($directory.'wp-admin/includes/plugin.php');

				activate_plugins(array_keys(get_plugins()));

			break;

			case "success" :

				require_once($directory.'wp-load.php');
				require_once($directory.'wp-admin/includes/upgrade.php');

				if (! empty($_POST['permalink_structure'])) {
					file_put_contents($directory.'.htaccess' , null);
					flush_rewrite_rules();
				}

				echo '<a href="'.admin_url().'" class="button" style="margin-right:5px;" target="_blank">Iniciar sesión</a>';
				echo '<a href="'.home_url().'" class="button" target="_blank">Ir a la página de Inicio</a>';

				removeDirectory(WPQI_ABSPATH.$path.'wp-install', true);

				break;
	}
}
else { ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
	<head>
		<meta charset="utf-8" />
		<title>Instalador Wordpress</title>
		<meta name="robots" content="noindex, nofollow">
		<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&#038;subset=latin%2Clatin-ext&#038;ver=3.9.1" />
		<link rel="stylesheet" href="assets/css/style.min.css" />
		<link rel="stylesheet" href="assets/css/buttons.min.css" />
		<link rel="stylesheet" href="assets/css/bootstrap.min.css" />
	</head>
	<body class="wp-core-ui">
	<img src="assets/images/logo.png" style="width: 100px; display: block; margin: 40px auto;" />
	<div id="errors"></div>
		<?php
		$parent_dir = realpath(dirname (dirname(__FILE__)));
		if (is_writable($parent_dir)) { ?>

			<div id="response"></div>
			<div class="progress" style="display:none;">
				<div class="progress-bar progress-bar-striped active" style="width: 0%;"></div>
			</div>
			<div id="success" style="display:none; margin: 10px 0;">
				<h1 style="margin: 0">El mundo es tuyo</h1>
				<p>Instalación correcta</p>
			</div>
			<form method="post" action="">

				<h1>Base de datos</h1>
				<p>Introduce la información relativa a la base de datos.</p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="dbname">Nombre</label></th>
						<td><input name="dbname" id="dbname" type="text" size="25" class="required" value="KD7Twggq0nRNvMnB" /></td>
						<td>Nombre de la base de datos</td>
					</tr>
					<tr>
						<th scope="row"><label for="uname">Usuario</label></th>
						<td><input name="uname" id="uname" type="text" size="25" class="required" value="Bc4unwNsht45i03M" /></td>
						<td>Usuario de la base datos</td>
					</tr>
					<tr>
						<th scope="row"><label for="pwd">Contraseña</label></th>
						<td><input name="pwd" id="pwd" type="text" size="25" class="required" value="shAk_4)N^Z8izW^<f%9pbzZa" /></td>
						<td>...y su contraseña</td>
					</tr>
					<tr>
						<th scope="row"><label for="dbhost">Servidor MySQL</label></th>
						<td><input name="dbhost" id="dbhost" type="text" size="25" value="localhost" class="required" /></td>
						<td>Servidor de la base de datos, por defecto localhost</td>
					</tr>
					<?php
					$seed = captureCharacter('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_');
					shuffle($seed);
					$rand = '';
					foreach (array_rand($seed, 8) as $k) $rand .= $seed[$k];
					?>
					<tr>
						<th scope="row"><label for="prefix">Prefijo</label></th>
						<td><input name="prefix" id="prefix" type="text" value="<?= $rand ?>" size="25" class="required" /></td>
						<td>Prefijo de las tablas que utilizará WordPress</td>
					</tr>
					<tr>
						<td colspan="3">
							<label><input type="checkbox" name="default_content" id="default_content" value="1" checked="checked" /> Marca esta opción si quieres vaciar WordPress y borrar todo el contenido de prueba</label>
						</td>
					</tr>
				</table>

				<h1>Instalación</h1>
				<p>Completa los siguientes campos para proceder con la instalación de WordPress</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="directory">Directorio</label>
						</th>
						<td>
							<input name="directory" type="text" id="directory" size="25" value="" />
							<p>Directorio de instalación. En blanco para instalar en el directorio raíz.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="directory">Directorio del tema</label>
						</th>
						<td>
							<input name="theme_directory" type="text" id="theme_directory" size="25" value="" class="required" />
							<p>Directorio del tema. En minúsculas, sin acentos ni espacios.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weblog_title">Título del sitio</label></th>
						<td><input name="weblog_title" type="text" id="weblog_title" size="25" value="" class="required" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="user_login">Usuario</label></th>
						<?php
						$seed = captureCharacter('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_ .-@');
						shuffle($seed);
						$rand = '';
						foreach (array_rand($seed, 16) as $k) $rand .= $seed[$k];
						?>
						<td>
							<input name="user_login" type="text" id="user_login" size="25" value="<?= $rand ?>" class="required" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="admin_password">Contraseña</label>
						</th>
						<?php
						$seed = captureCharacter('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_ .-,:;ñÑ&%$#@!*=)({}[]');
						shuffle($seed);
						$rand = '';
						foreach (array_rand($seed, 20) as $k) $rand .= $seed[$k];
						?>
						<td>
							<input name="admin_password" type="text" id="admin_password" size="25" value="<?= $rand ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="admin_email">Correo electrónico</label></th>
						<td><input name="admin_email" type="text" id="admin_email" size="25" class="required" value="programacion@germinalbrandonlove.com" />
						</td>
					</tr>
				</table>

				<h1>Extensiones</h1>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="plugins">Plugins</label>
						</th>
						<td>
							<input name="plugins" type="text" id="plugins" size="50" value="wordpress-seo; elementor; wp-power-stats" />
							<p>Nombre utilizado en el repositorio de Wordpress, separados por punto y coma.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plugins">Otros plugins</label>
						</th>
						<td>
							<label><input type="checkbox" checked id="plugins_premium" name="plugins_premium" value="1" /> Instalación de plugins propios</label>
							<p>Se instalarán los archivos zip alojados en el directorio <em>wp-install/plugins</em>.</p>
						</td>
					</tr>
				</table>

				<p class="step"><span id="submit" class="button button-large">Instalar</span></p>

			</form>

			<script src="assets/js/jquery-1.8.3.min.js"></script>
			<script>var data = <?php echo $data; ?>;</script>
			<script src="assets/js/script.js"></script>
		<?php
		} else { ?>

			<div class="alert alert-error" style="margin-bottom: 0px;">
				<strong><?php echo _('Error');?></strong>
				<p style="margin-bottom:0px;"><?php echo 'No dispones de permisos en '.basename($parent_dir).'. Por favor, corrige los permisos para continuar.' ;?></p>
			</div>

		<?php
		}
		?>
	</body>
</html>
<?php
}
