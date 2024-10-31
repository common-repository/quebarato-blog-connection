<?php
/*
 Plugin Name: QueBarato! Blog Connection
 Plugin URI: http://www.quebarato.com.br/
 Description:  Ganhar dinheiro através de um post em seu blog parece a idéia perfeita? 
 O QueBarato!  apresenta o QueBarato! Blog Connection, o plugin que estava faltando para você monetizar seu blog de uma maneira muito fácil e rápida. 
 Cada post gerará um anúncio no QueBarato! automaticamente. Não perca esta audiência: são mais de 7 milhões de visitas mensais!
 Version: 1.0
 Author: Wilson da R. França, Iuri M. Iovanovich, Bruno A. Polidoro
 Author URI: http://ajuda.quebarato.com.br/sobre-o-quebarato.html


 QueBarato! é parte do Grupo BuscaPé Inc. © Copyright 2006 QueBarato! Anunciar aqui é grátis

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

include 'QueBaratoBaseAPI.class.php';

if (!class_exists('PostAd')) :
class PostAd {

	private $this_post;
	
	private $QB_API_URI = 'http://api.quebarato.com';
	
	public function __construct() {

		$this->setUp();

		add_action('admin_menu', array(&$this, 'start'));

		add_action('publish_post', array(&$this, 'save'));
		
		add_action('wp_head', array(&$this, 'add_meta'));
		
		remove_action('wp_head', 'rel_canonical');
		
	}


	public function setUp() {

		define('POSTAD_VERSION', '1.0');
		define('POSTAD_HOME', 'http://www.quebarato.com');
		define('POSTAD_FILE', plugin_basename(dirname(__FILE__)));
		define('POSTAD_ABSPATH', str_replace('\\', '/', WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__))));
		define('POSTAD_URLPATH', WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)));

	}


	public function start(){

		add_action('admin_print_styles', array(&$this, 'loadCSS'));
		
		add_action( 'admin_init', array(&$this, 'loadScripts'));

		add_meta_box('post-meta-boxes', 'QueBarato! Blog Connection', array(&$this, 'displayMetaBox'), 'post', 'normal', 'high');

		$this->setup_settings();

		$this->start_plugin_settings_page();

		$this->post_ad_admin_warnings();

	}

	public function setup_settings(){

		register_setting( 'post_ad_options', 'post_ad_options', array(&$this, 'post_ad_options_validate'));

		add_settings_section('post_ad_main_settings', 'Configurações', array(&$this, 'post_ad_section_text'), 'post_ad_plugin');
		
		delete_option('post_ad_api_key');
		
		add_option('post_ad_api_key', 'bd1291a376bef2548e8e7856edb10be1', '', 'yes');

	}

	public function post_ad_section_text() {
		echo '<h4>Preencha com os seus dados do QueBarato.</h4>';
	}

	public function post_ad_username_setting(){
		$options = get_option('post_ad_options');
		echo "<input size='40' id='post_ad_username' name='post_ad_options[post_ad_username]' type='text' value='{$options['post_ad_username']}' />";
	}

	public function post_ad_password_setting(){
		$options = get_option('post_ad_options');
		echo "<input size='40' id='post_ad_password' name='post_ad_options[post_ad_password]' type='password' value='{$options['post_ad_password']}' />";
	}

	public function post_ad_options_validate($input){

		return $input;
	}

	function show_post_ad_settings_page(){?>
<div class="config_qb_api">
	<form method="POST" action="options.php">
	<?php settings_fields('post_ad_options'); ?>
	<?php do_settings_sections('post_ad_plugin'); ?>
	<?php $options = get_option('post_ad_options');
	echo '<p>Nome de usuário: <input id="post_ad_username" name="post_ad_options[post_ad_username]" type="text" value="'.$options['post_ad_username'].'" /></p>';
	echo '<p>Senha: <input id="post_ad_password" name="post_ad_options[post_ad_password]" type="password" value="'.$options['post_ad_password'].'" /></p>';
	?>
		<p>
			<input name="Submit" type="submit"
				value="<?php esc_attr_e('Salvar'); ?>" class="bt_save"/>
		</p>
	</form>
</div>
	<?php }

	public function start_plugin_settings_page(){

		add_options_page('QueBarato! Blog Connection', 'QueBarato! Blog Connection', 'manage_options', __FILE__, array(&$this, 'show_post_ad_settings_page'),plugins_url('/images/icon.png', __FILE__));
	}

	public function loadScripts() {
		wp_register_script( 'maskmoney', plugins_url('/jquery.maskmoney.js', __FILE__) );
		wp_enqueue_script( 'maskmoney');
		
		wp_register_script( 'numberformater', plugins_url('/jquery.numberformatter-1.2.2.min.js', __FILE__) );
		wp_enqueue_script( 'numberformater');
		
	}

	public function loadCSS() {
		wp_enqueue_style('related-css', POSTAD_URLPATH .'/css.css', false, POSTAD_VERSION, 'all');
	}

	public function has_post_errors($post_id){

		$result_code_array = get_post_meta($post_id, '_qb_result_code');

		error_log("Result code array: ".$result_code_array);

		$v = var_export($result_code_array, true);

		error_log($v);

		$result_code = $result_code_array[0];

		error_log("Result code: ".$result_code);

		if($result_code == 400 || $result_code == 500 || $result_code == 503 || $result_code == 404) return true;

		else return false;
	}

	public function is_post_ok($post_id){

		$result_code_array = get_post_meta($post_id, '_qb_result_code');

		$result_code = $result_code_array[0];

		if($result_code == 201) return true;

		else return false;
	}

	public function post_ad_admin_warnings() {

		global $post, $post_ID;

		$options = get_option('post_ad_options');

		$username = $options['post_ad_username'];

		$password = $options['post_ad_password'];

		if (!isset($username) || !isset($password) || empty($username) || empty($password) || !$this->logged()) {

			function post_ad_warning() {
				echo "
			<div id='postad-warning' class='error fade'><p><strong>".__('Você não pode publicar um anúncio no QueBarato ainda!')."</strong> ".sprintf(__('Você deve<a href="%1$s"> logar-se </a> para poder publicar.'), "options-general.php?page=quebarato-blog-connection/postad.php")."</p></div>
			";
			}

			add_action('admin_notices', 'post_ad_warning');

			return;
		} else if ( get_option('post_ad_connectivity_time') && empty($_POST) && is_admin() && !post_ad_connectivity_ok() ) {
			function post_ad_warning() {
				echo "
			<div id='postad-warning' class='error fade'><p><strong>".__('Você não poderá publicar um anúncio no QueBarato.')."</strong> ".sprintf(__('Ocorreu algum erro de rede que impede você de publicar um anúncio no momento.  Alguarde e tente novamente em instantes.'))."</p></div>";
			}
			add_action('admin_notices', 'post_ad_wmaskmoneyarning');
			return;
		}
	}

	public function pos_post_warning($post, $post_ID){

		$post_status = $post->post_status;

		if($this->has_post_errors($post_ID) && $post_status == 'publish'){

			$qb_messages = get_post_meta($post_ID, '_qb_message');

			foreach ($qb_messages as &$value) {
				echo "<div id='postad-warning' class='error fade'><p><strong>".__('Não foi possível publicar seu anúncio no QueBarato! ')."</strong> ".sprintf(__($value))."</p></div>";
			}


		} else if($this->is_post_ok($post_ID) && $post_status == 'publish'){
			
			$url = get_post_meta($post_ID, '_qb_post_uri');
			
			echo "<div id='postad-warning' class='updated fade'><p><strong>".__('Anúncio publicado no QueBarato com sucesso! ')."</strong> ".sprintf(__('<a href="%1$s">ver</a>'), (isset($url) ? $url[0] : ""))."</p></div>";

		}
	}

	public function logged(){

		$options = get_option('post_ad_options');

		$username = $options['post_ad_username'];

		$password = $options['post_ad_password'];
		
		$api_key = get_option('post_ad_api_key');
		
		error_log($this->QB_API_URI);
		
		$api = new QueBaratoBaseAPI($this->QB_API_URI, $api_key);

		$ret = $api->getAuthorized('/v1/user?username='.$username, null, $username, $password);

		$retArray = (array) $ret;

		$obj = $retArray['result'];

		$contact_info = $obj->{'contactInfo'};

		$email_primary = $contact_info->{'emailPrimary'};

		return (isset($email_primary) && !empty($email_primary));

	}

	public function post_ad_connectivity_ok(){

		$api_key = get_option('post_ad_api_key');

		$api = new QueBaratoBaseAPI($this->QB_API_URI, $api_key);

		$ret = $api->head('/v1/ad');

		return ($ret != null && $ret['resutlCode'] == 200);

	}
	
	

	public function displayMetaBox(){

		global $post, $post_ID;
		
		$this->pos_post_warning($post, $post_ID);
		
		if($this->is_post_ok($post_ID)){
			$ad_obj = $this->get_post_ad($post_ID);
		}

		?>
<script language="Javascript" type="text/javascript">	
jQuery(document).ready(function($) {
  	
 	function setHeader(xhr) {

 	 	<?php 
 	 		$api_key = get_option('post_ad_api_key');
 	 	?>
 	 	
    	xhr.setRequestHeader('X-QB-Key', '<?php echo $api_key; ?>');
 	}

	function root() { 

		var categoryServiceUri = '<?php echo $this->QB_API_URI.'/v1/category'?>';

		var estrutura = $('.tree_select li');

		var apiKey = '<?php echo $api_key;?>';
		
		$.ajax({
	         url: categoryServiceUri,
	         type: 'GET',
	         dataType: 'json',
	         success: function(categories) {
	        	 var options = '';
	 			$.each(categories, function(i, obj) {
	 				var rootName = obj.name;
	 				var rootUri = obj.href;
	 				options += '<option value="' + rootUri + '">' + rootName + '</option>';	
	 			});

	 			$(".selectG:first").append(options);
		          
		     },
	         error: function() {
		          
		     },
	         beforeSend: setHeader
	   });
	}
	
	$(".selectG:first").live('change', function(){
		$("#qb_category").val("");
		attributes($(this));
	});

	$(".selectG").live('change', function(){
		$("#qb_category").val("");
		children($(this));
	});
	
	function children(el){
		
		var estrutura = $('.tree_select li:last').clone();
		var categoryServiceUri = '<?php echo $this->QB_API_URI.'/v1';?>';
		el.closest('li').nextAll().remove();
		el.find("option:selected").each(function () {
			if($(this).val() != 'option'){
				$.ajax({
			         url: categoryServiceUri + $(this).val(),
			         type: 'GET',
			         dataType: 'json',
			         success: function(data){
							if(data.children != null){
								var options = ''; 
								
								$.each(data.children, function(i,obj) { 
									var childName = obj.name;
									var childUri = obj.href;
									options += '<option value="' + childUri + '">' + childName + '</option>';	
								});
								
								$('option', estrutura).not('option:first').remove();
								$('.selectG', estrutura).append(options);
								$('#category_selects').append(estrutura);
								
							} else {
								
								$("#qb_category").val(data.id);
								
							}	
						},
			         error: function() {
				          
				     },
			         beforeSend: setHeader
			    });
			}
		  });
	}
	
	function attributes(root){

		root.find("option:selected").each(function () {
			
			var rootUri = root.val();
			
			if(rootUri == "/category/8" || rootUri == "/category/3"){
				
				$(".preco input,.condition input, .payment input").attr("disabled", "disabled");
				
			} else if(rootUri == "/category/4" || rootUri == "/category/6"){
				
				$(".condition input").attr("disabled", "disabled");
				
				$(".preco input, .payment input").removeAttr("disabled");
				
			} else {
				
				$(".preco input,.condition input, .payment input").removeAttr("disabled");
				
			}
		});
		
	}

	$('#preco_qb').maskMoney({
		precision: 2,
	    symbol: 'R$',
	    decimal: ',',
	    thousands: '',
	    allowZero: true,
	    symbolStay: true,
	    showSymbol: true
	});

	

root();
});
	
</script>
<div
	class="qb_api_wordpress">

	<div class="line section10">
		<div class="middleCol">
			<div class="line separator">
				<ul id="category_selects" class="tree_select">
					<li id="categoryselect">
						<div class="line">
							<div class="unit">
								<?php if (!$this->is_post_ok($post_ID)){?>
								<select class="selectG active_text_filled">
									<option value="option">Selecione....................................................</option>
								</select>
								<?php } else {
									
									$category = $ad_obj->{'category'};
									
									echo '<p class="mtop5">'.$category->{'name'}.'</p>';
									
								}?>
							</div>
							<div class="unit">
							<?php
							echo '<img style="display: none;"
									src="'.get_option('siteurl').'/wp-content/plugins/postad/loading.gif"
									class="loading">'; ?>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</div>
		<div class="leftCol">
			<p>
				<?php if (!$this->is_post_ok($post_ID)){?>
				<strong>Escolha uma categoria:</strong>
				<?php } else {?>
				<strong>Categoria:</strong>
				<?php }?>
			</p>
		</div>
	</div>

	<!-- INICIO CEP -->
	<div class="line section cep">
		<div class="middleCol">
			<?php if (!$this->is_post_ok($post_ID)){?>
			<input type="text" value="" class="textboxG" name="cep">
			<?php } else {
				
				$locale = $ad_obj->{'locale'};
									
				echo '<p class="mtop3">'.$locale->{'country'}.', '.$locale->{'state'}.', '.$locale->{'city'}.'</p>';
			}?>
		</div>
		<div class="leftCol">
			<p>
				<?php if (!$this->is_post_ok($post_ID)){?>
				<strong>CEP:</strong>
				<?php } else {?>
				<strong>Localidade:</strong>
				<?php }?>
			</p>
		</div>
	</div>
	<!-- FIM CEP -->

	<!-- INICIO PRECO -->
	<div class="line section preco">
		<div class="middleCol">
			<?php if (!$this->is_post_ok($post_ID)){?>
			<input type="text" class="textboxG" name="preco" id="preco_qb">
			<?php } else {
				
				$price = $ad_obj->{'price'};

				if(isset($price->{''})){
					echo '<p class="mtop3"> R$ '.$price->{'amount'}.'</p>';
				} else {
					echo '<p class="mtop3">--</p>';
				}
			}?>
		</div>
		<div class="leftCol">
			<p>
				<strong>Preço:</strong>
			</p>
		</div>
	</div>
	<!-- FIM PRECO -->

	<!-- INICIO ESTADO DO PRODUTO -->
	<div class="line section condition">
		<div class="middleCol">
			<div class="line mtop5">
				<div class="unit size1of5">
					<div class="line">
						<div class="unit">
							<input type="radio" tabindex="2" value="Novo" <?php if ($this->is_post_ok($post_ID) && $ad_obj->{'condition'} == 'Novo'){ echo 'checked="checked"';}?>
								name="condition" class="radio_bt" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label for="" class="radio_label bold_label">NOVO</label>
						</div>
					</div>
				</div>
				<div class="unit ">
					<div class="line">
						<div class="unit">
							<input type="radio" tabindex="2" value="Usado" <?php echo 'att="'.$ad_obj->{'condition'}.'"'; if ($this->is_post_ok($post_ID) && $ad_obj->{'condition'} == 'Usado'){ echo 'checked="checked"';}?>
								name="condition" class="radio_bt" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label for="" class="radio_label bold_label">USADO</label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="leftCol">
			<p>
				<strong>Estado do Produto:</strong>
			</p>
		</div>
	</div>
	<!-- FIM ESTADO DO PRODUTO -->

	<!-- INICIO FORMAS DE PAGAMENTO -->
	<div class="line section payment">
		<div class="middleCol">
			<ul class="line bold_labels size300">
				<?php if (!$this->is_post_ok($post_ID)){?>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="1" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">Dinheiro</label>
						</div>
					</div>
				</li>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="2" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">Depósito Bancário</label>
						</div>
					</div>
				</li>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="3" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">Cheque</label>
						</div>
					</div>
				</li>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="4" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">Cartão de Crédito</label>
						</div>
					</div>
				</li>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="5" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">Sedex a Cobrar</label>
						</div>
					</div>
				</li>
				<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							<input type="checkbox" tabindex="6" class="checkbox_bt"
								name="payment_method[]" value="6" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
						</div>
						<div class="unit">
							<label class="checkbox_label">A Combinar</label>
						</div>
					</div>
				</li>
			<?php } else {
				
				$payments = $ad_obj->{'paymentMethods'};
				
				foreach ($payments as $payment){
				?>
			
			<li class="unit size1of37">
					<div class="line">
						<div class="unit">
							
						</div>
						<div class="unit">
							<label class="checkbox_label"><?php echo $payment->{'name'}?></label>
						</div>
					</div>
			</li>
			<?php }}?>
			</ul>
		</div>
		<div class="leftCol">
			<p>
				<strong>Forma de Pagamento:</strong>
			</p>
		</div>
	</div>
	<!-- FIM FORMAS DE PAGAMENTO -->

	<!-- INICIO ON/OFF PLUGIN -->
	<div class="line section on_off">
		<div class="middleCol">
			<div class="line">
				<div class="unit">
					<input type="checkbox" tabindex="6" value="true" id="combinar"
						name="combinar" class="checkbox_bt" <?php if ($this->is_post_ok($post_ID)){ echo 'disabled="disabled"'; }?>>
				</div>
				<div class="unit">
					<label class="checkbox_label" for="combinar">Quero publicar este
						anúncio no QueBarato!</label>
				</div>
			</div>
		</div>
		<div class="leftCol emptyCol">
			<p>
				<strong></strong>
			</p>
		</div>
	</div>
	<!-- FIM ON/OFF PLUGIN -->

	<input type="hidden" id="qb_category" name="qb_category" />

</div>
<?php

	}

	public function save(){

		global $post, $post_ID;

		if(isset($_POST) && $_POST['combinar']){
					
			$options = get_option('post_ad_options');

			$username = $options['post_ad_username'];

			$password = $options['post_ad_password'];

			$category = $_POST['qb_category'];

			$cep = $_POST['cep'];

			$price = $_POST['preco'];
			
			$float_price = floatval(str_replace(",",".",ereg_replace("[^-0-9,]", "", $price)));
			
			if(isset($float_price)) $price_json = '"price":{ "amount":"'.$float_price.'", "currency": "BRL" }';

			$condition = $_POST['condition'];

			if(isset($condition)) $condition_json = '"condition":"'.$condition.'"';

			$payment_methods = $_POST['payment_method'];

			$payment_json;

				
			if(isset($payment_methods)){

				$payment_json = '"paymentMethods" : [';

				$total_payment_methods = count($payment_methods);

				$counter = 0;

				foreach ($payment_methods as &$value) {

					$payment_json = $payment_json.'{ "href": "/payment-method/offline/'.$value.'" } ';

					if($counter < $total_payment_methods - 1) $payment_json = $payment_json.',';

					$counter++;

				}

				$payment_json = $payment_json.']';

			}
				
			$badChars = array("\r","\n", "\t");

			$title = str_replace($badChars, " ", $_POST['post_title']);

			$post_description = str_replace($badChars, " ", strip_tags($_POST['content'], '<b><p><ul><ol><li><i><u>'));

			update_post_meta($post_ID, '_qb_data', $data);

			update_post_meta($post_ID, '_qb_category', $category);

			update_post_meta($post_ID, '_qb_locale', $cep);

			if(isset($price)) update_post_meta($post_ID, '_qb_price', $price);

			if(isset($condition)) update_post_meta($post_ID, '_qb_condition', $condition);

			if(isset($payment_methods)) update_post_meta($post_ID, '_qb_payment_methods', $payment_methods);

			update_post_meta($post_ID, '_qb_title', $title);

			update_post_meta($post_ID, '_qb_description', $post_description);
			
			$api_key = get_option('post_ad_api_key');
				
			$api = new QueBaratoBaseAPI($this->QB_API_URI, $api_key);

			$data = '{"title":"'.$title.'","description":"'.$post_description.'","category": { "href":"/category/'.$category.'"}'.',"locale" : {"zip" : "'.$cep.'"}'.(isset($condition_json) ? ','.$condition_json : '').''.(isset($price_json) ? ','.$price_json : '').''.(isset($payment_json) ? ','.$payment_json : '').'}';
				
			update_post_meta($post_ID, '_qb_post_data', $data);
				
			$ret = $api->post('/v1/ad', $data, $username, $password, 'application/json');

			$ret_array = (array) $ret;

			$return_code = $ret_array['resultCode'];

			update_post_meta($post_ID, '_qb_result_code', $return_code);

			$return_body =  $ret_array['result'];

			$trace_obj = $return_body->{'trace'};
				
			$headers = $ret_array['header'];
			
			$extra = $headers['extra'];
			
			$location = $extra->{'Location'};
			
			$ret_ad = $api->get('/v1'.$location);
			
			$ret_ad_array = (array)$ret_ad;
			
			$ad = $ret_ad_array['result'];
			
			$url = $ad->{'skinUrl'};
									
			update_post_meta($post_ID, '_qb_post_location', $location);
			
			update_post_meta($post_ID, '_qb_post_uri', $url);

			$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $post_ID ); 
			
			$attachments = get_posts( $args );
			
			$uploads = wp_upload_dir();
			
			if ($attachments) {
				
				foreach ( $attachments as $attachment ) {
					
					$file_uri = get_post_meta($attachment->ID, '_wp_attached_file');
					
					error_log($uploads['basedir'].'/'.$file_uri[0]);
					
					$image = $uploads['basedir'].'/'.$file_uri[0];
					
					$image_data = array("image"=>"@$image");
					
					$ad_location = get_post_meta($post_ID, '_qb_post_location');
					
					$image_post_return = $api->post('/v1'.$ad_location[0].'/media/image', $image_data, $username, $password, "multipart/form-data");
					
					update_post_meta($post_ID, '_qb_img_post', $image_post_return);		
				}
			}
			
			if ($return_code == 400 || $return_code == 500){
					
					
				$message = '';

				foreach($trace_obj as &$value){
					$message = $message.' <p> '.$value.' </p> ';
				}
					
				update_post_meta($post_ID, '_qb_message', $message);

			}

		}

	}
	
	
	public function add_meta(){
		
		global $post, $post_ID;
			
		$post_uri = get_post_meta($post->ID, '_qb_post_uri');
		
		if((is_single() || is_page()) && $post_uri != null && $post_uri != ''){
			
			$url = $post_uri[0];
		
			echo '<link rel="canonical" href="'.$url.'" />';
			
		} else {
			
			echo '<link rel="canonical" href="' . get_permalink() . '" />';
			
		}
	}
	
	public function get_post_ad($post_ID){
		
		$location = get_post_meta($post_ID, '_qb_post_location');
		
		$api_key = get_option('post_ad_api_key');
		
		$api = new QueBaratoBaseAPI($this->QB_API_URI, $api_key);
		
		$ret_ad = $api->get('/v1'.$location[0]);
			
		$ret_ad_array = (array)$ret_ad;
			
		$ad = $ret_ad_array['result'];
		
		return $ad;
		
	}
}

endif;

global $postad;

$postad = new PostAd();
?>