$(document).ready(function() {

	var $response  = $('#response');

	$('#submit').click( function() {

		errors = false;

		$('#errors').hide().html('<strong>Importante</strong>');

		$('input.required').each(function(){
			if ( $.trim($(this).val()) == '' ) {
				errors = true;
				$(this).addClass('error');
				$(this).css("border", "1px solid #FF0000");
			} else {
				$(this).removeClass('error');
				$(this).css("border", "1px solid #DFDFDF");
			}
		});

		if ( ! errors ) {
			$.post(window.location.href + '?action=check_before_upload', $('form').serialize(), function(data) {

				errors = false;
				data = $.parseJSON(data);

				if ( data.db == "error etablishing connection" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; Error estableciendo una conexión con la base de datos.</p>');
				}
				if ( data.wp == "error directory" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; Parece que wordpress ya está instalado.</p>');
				}
				if ( ! errors ) {
					$('form').fadeOut( 'fast', function() {
						$('.progress').show();
						$response.html("<p>Descargando Wordpress...</p>");
						$.post(window.location.href + '?action=download_wp', $('form').serialize(), function() {
							unzip_wp();
						});
					});
				} else {
					$('html,body').animate( { scrollTop: $( 'html,body' ).offset().top } , 'slow' );
				}
			});

		} else {
			$('html,body').animate( { scrollTop: $( 'input.error:first' ).offset().top-20 } , 'slow' );
		}
		return false;
	});

	function unzip_wp() {
		$response.html("<p>Descomprimiendo archivos...</p>" );
		$('.progress-bar').animate({width: "16.5%"});
		$.post(window.location.href + '?action=unzip_wp', $('form').serialize(), function(data) {
			wp_config();
		});
	}

	function wp_config() {
		$response.html("<p>Creando los archivos de configuración...</p>");
		$('.progress-bar').animate({width: "33%"});
		$.post(window.location.href + '?action=wp_config', $('form').serialize(), function(data) {
			install_wp();
		});
	}

	function install_wp() {
		$response.html("<p>Creando y configurando la base de datos...</p>");
		$('.progress-bar').animate({width: "49.5%"});
		$.post(window.location.href + '/wp-admin/install.php?action=install_wp', $('form').serialize(), function(data) {
			install_theme();
		});
	}

	function install_theme() {
		$response.html("<p>Instalando tema principal y creando el tema hijo...</p>");
		$('.progress-bar').animate({width: "66%"});
		$.post(window.location.href + '/wp-admin/install.php?action=install_theme', $('form').serialize(), function(data) {
			install_plugins();
		});
	}

	function install_plugins() {
		$response.html("<p>Instalando extensiones...</p>");
		$('.progress-bar').animate({width: "82.5%"});
		$.post(window.location.href + '?action=install_plugins', $('form').serialize(), function(data) {
			$response.html(data);
			success();
		});
	}

	function success() {
		$response.html("<p>Instalación completada</p>");
		$('.progress-bar').animate({width: "100%"});
		$response.hide();
		$('.progress').delay(500).hide();
		$.post(window.location.href + '?action=success',$('form').serialize(), function(data) {
			$('#success').show().append(data);
		});
	}
});