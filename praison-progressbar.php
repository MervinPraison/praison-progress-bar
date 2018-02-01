<?php
/**
 * @package Praison Progress Bar
 * @version 1.0
 */
/*
Plugin Name: Praison Progress Bar
Plugin URI: https://praison.com/
Description: Praison Big Data Progress Bar
Author: Mervin Praison
Version: 1.0
Author URI: https://praison.com/
*/


class Praison_Progressbar{
  
	private $my_plugin_screen_name;
	private static $instance;
  
	static function GetInstance()
	{
	  
	  if (!isset(self::$instance))
	  {
	      self::$instance = new self();
	  }
	  return self::$instance;
	}
      
    public function PluginMenu()
    {
     	$this->my_plugin_screen_name = add_menu_page(
                                        __( 'Home', 'textdomain' ), 
                                        __( 'Home', 'textdomain' ), 
                                        'manage_options',
                                        'praison-home', 
                                        array($this, 'RenderPage'), 
                                        'dashicons-store'
                                        );
      
    	add_submenu_page('praison-home', __( 'About', 'textdomain' ), __( 'About', 'textdomain' ), 'manage_options', 'praison-about', array($this, 'RenderAboutPage'));   		
    }

	public function RenderPage(){
		?>
		<div class='wrap'>
		<h2>Home</h2>

<style>
 #progress {
 width: 500px;
 border: 1px solid #aaa;
 height: 15px; 
 overflow:hidden;
 }
 #progress .bar {
 background-color: #bbd;
 height: 15px;
 }
 </style>

<script>
( function( $ ) {
  $('body').on( 'submit', '.wisdom-ajax-params', function(e) {
    e.preventDefault();
    $('.wisdom-run-query').prop( 'disabled', true );
    var params = $(this).serialize();
    console.log(params);
    $('.wisdom-batch-progress').append( '<div class="spinner is-active"></div>' );
    // start the process
    self.process_offset( 0, params, self );
  });

  process_offset = function( offset, params, self ) {
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        params: params,
        action: 'wisdom_do_batch_query',
        offset: offset,
      },
      dataType: "json",
      success: function( response ) {
        if( 'done' == response.offset ) {
        	$('.wisdom-batch-progress').append(response);
        	//$("#progress").html('<div class="bar" style="width:100%"></div>');
        	$('.wisdom-batch-progress').hide();
          //window.location = response.url;
        } else {
        	console.log(response);
        	$("#progress").html('<div class="bar" style="width:' + (response.offset/response.total)*100 + '%"></div>');
          self.process_offset( parseInt( response.offset ), params, self );
        }
      }
    });
  }
}( jQuery ) );

</script>

<form class="wisdom-ajax-params" action="#">
	<?php wp_nonce_field('wisdom_batch_query', 'wisdom_batch_query'); ?>
	<input type="text" name="action" disabled value="wisdom_do_batch_query">
	<input type="text" name="transient" value="progressbar">
	<!-- <input type="text" name="wisdom_plugin" value="zeo_title"> -->
	<input type="submit" class="wisdom-run-query" value="Submit" name="submit">
</form>

<?php //print_r(get_transient( 'progressbar' )); ?>

<div id="progress"></div>

<div class="wisdom-batch-progress" style="float:left;"></div>

		</div>

		<?php
	}
	public function RenderAboutPage(){
		?>
		<div class='wrap'>
		<h2>About</h2>
		</div>
		<?php
	}     
    

	public function InitPlugin()
	{
	   add_action('admin_menu', array($this, 'PluginMenu'));
	}
  
 }
 
$Praison_Menu = Praison_Progressbar::GetInstance();
$Praison_Menu->InitPlugin();


add_action( 'wp_ajax_wisdom_do_batch_query', 'wisdom_do_batch_query' );

function wisdom_do_batch_query() {
  $offset = absint( $_POST['offset'] );
  parse_str( $_POST['params'], $params );
  $params = (array) $params;
  $increment = 900; // You can set your increment value here
  if( ! wp_verify_nonce( $params['wisdom_batch_query'], 'wisdom_batch_query' ) ) {
    //die(); // You'll need to ensure you've set a nonce in your form
  }
  $transient = esc_attr( $params['transient'] );
  $plugin_data = array();
  // Record all data in big array
  if( $offset == 0 ) {
    // This is our first pass
    // Save some useful data for the query
    $plugin_data['plugin_ids'] = wisdom_get_query_ids( $params );
    $plugin_data['total_plugins'] = count( $plugin_data['plugin_ids'] );
    // Ensure any existing transient is deleted
    delete_transient( $transient );
  } else {
    // We save the data to this transient in blocks, then pick it back up again at the start of a new batch
    $plugin_data = get_transient( $transient );
    // Set timestamp $plugin_data['last_run'] = time();
  }
  $url = '';
  if( $offset > $plugin_data['total_plugins'] ) {
    $offset = 'done';
    $args = array(
      'page' => 'praison-home',
      //'page' => $params['page'], // Set other params here as needed
    );
    $url = add_query_arg( $args, admin_url( 'admin.php' ) );
  } else {
    // Query the tracked-plugins
    $args = array(
      'post_type' => 'post',
      'posts_per_page' => $increment,
      'offset' => $offset,
      'fields' => 'ids',
      'no_found_rows' => true,
      'post__in' => $plugin_data['plugin_ids']
    );
    $plugins = get_posts( $args );
    foreach( $plugins as $plugin_id ) {
      // Break these up so we only collect the data we need for each report
      $plugin_slug = get_post_meta( $plugin_id, 'zeo_title', true );      
      if( ! empty( $plugin_slug ) ) {
        if( isset( $plugin_data['slugs'][esc_attr( $plugin_slug )] ) ) {
          $plugin_data['slugs'][esc_attr( $plugin_slug )]++;
        } else {
          $plugin_data['slugs'][esc_attr( $plugin_slug )] = 1;
        }
      }
    }
    $offset += $increment; // You can set your increment value here
    set_transient( $transient, $plugin_data );
  }
  echo json_encode( array( 'offset' => $offset, 'url' => $url, 'total' => $plugin_data['total_plugins'] ) );
  exit;
}

// Returns the array of post IDs to be used by the batch query
function wisdom_get_query_ids( $params=array() ) {
  // Query the IDs only for all tracked-plugins
  $args = array(
    'post_type' => 'post',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'post_status' => 'publish'
  );
  // An example of using the $params to refine the query
  if( ! empty( $params['wisdom_plugin'] ) && $params['wisdom_plugin'] != 'all' ) {
    // This will refine the query to only include meta_key / meta_value pairs
    $args['meta_query'][] = array(
      'key' => $params['wisdom_plugin'],
      //'value' => $params['wisdom_plugin'],
      //'value' => 'Ut',
      'compare' => 'EXISTS'
    );
  }
  $id_query = new WP_Query( $args );
  if( $id_query->have_posts() ) {
    return $id_query->posts;
  }
  return false;
}



?>
