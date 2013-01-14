<?php

if ( ! class_exists( 'PostTypeBuilder' ) ) {

class PostTypeBuilder {
	
	public $post_type;
	public $name;
	public $singular;
	public $supports = array( 'title', 'editor', 'comments', 'thumbnail');
	public $menu_icon;
	public $public = true;
	public $has_archive = true;
	public $fields = array(
		/*
			text
			textarea
			number
			wysiwyg
			email
			url
			date
			time
			datetime
			media
			color
			relationship
			radio
			checkbox
			checkboxes
			select
		*/
	);
	public $metaboxes = array();
	public $templates = array(
		'single-page' => null,
		'single-content' => null,
		'archive-page' => null,
		'archive-content' => null
	);
	public $query_vars = array();
	public $request_types = array('json');
	public $taxonomies = array('category', 'post_tag');
	
	public $taxonomy_filters = array();
	
	public function register(){	
		global $wp;
		
	    register_post_type($this->post_type,
	        array(
	            'labels' => array(
	                'name' => $this->name,
	                'singular_name' => $this->singular,
	                'add_new' => 'Add New',
	                'add_new_item' => 'Add New '.$this->singular,
	                'edit' => 'Edit',
	                'edit_item' => 'Edit '.$this->singular,
	                'new_item' => 'New '.$this->singular,
	                'view' => 'View',
	                'view_item' => 'View '.$this->singular,
	                'search_items' => 'Search '.$this->name,
	                'not_found' => 'No '.$this->name.' found',
	                'not_found_in_trash' => 'No '.$this->name.' found in Trash',
	                'parent' => 'Parent '.$this->singular
	            ),
	            'public' => $this->public,
	            'menu_position' => 15,
	            'supports' => $this->supports,
	            'taxonomies' => $this->taxonomies,
	            'menu_icon' => $this->menu_icon,
	            'has_archive' => $this->has_archive,
	            'rewrite' => array(
	            	'slug' => $this->slug ? $this->slug : strtolower($this->name)
	            )
	        )
	    );
	    
	    foreach($this->query_vars as $var){
	    	$wp->add_query_var($var);
	    }
	    
	    add_action('admin_init', array($this, 'admin_init'));
	    add_filter('template_include', array($this, 'template_include'), 1);
	}
	
	public function admin_init(){
		
		if(count($this->fields) > 0){
			add_filter('save_post', array($this, 'on_save'));
			
		}
		
		// manage columns
		add_filter('manage_edit-'.$this->post_type.'_columns', array($this, 'manage_edit_columns'));
		add_action('manage_posts_custom_column', array($this, 'manage_custom_column'));
		
		// shortable column
		add_filter('manage_edit-'.$this->post_type.'_sortable_columns', array($this, 'manage_shortable_columns'));
		//add_filter( 'request', 'column_ordering' );
		add_filter( 'request', array($this, 'column_orderby'));
		
		// taxonomy filter
		add_action( 'restrict_manage_posts', array($this, 'restrict_manage_posts'));
		add_filter( 'parse_query', array($this, 'perform_filtering'));
		
		
		foreach($this->metaboxes as $id => $value){
			add_meta_box( 
				$this->post_type.'_'.$id,
		        $value['title'],
		        array($this, 'metabox_'.$id),
		        $this->post_type,
		        $value['type'],
		        $value['priority']
		    );	
		}
		
		// title label
		add_filter('enter_title_here', array($this, 'title_label'));
		
		// send to editor
		add_filter('media_send_to_editor', array($this, 'media_send_to_editor'), 10, 3);
		
		// jquery ui
		wp_register_style('jquery.ui.theme', 'http://code.jquery.com/ui/1.9.2/themes/base/jquery-ui.css');
		
		// datetime picker
		wp_register_style('datetimepicker.style', 'https://github.com/trentrichardson/jQuery-Timepicker-Addon/raw/master/jquery-ui-timepicker-addon.css');
		wp_register_script('datetimepicker.js','https://raw.github.com/trentrichardson/jQuery-Timepicker-Addon/master/jquery-ui-timepicker-addon.js');
		
		// validator engine
		wp_register_script('validator.engine.js','https://raw.github.com/posabsolute/jQuery-Validation-Engine/master/js/jquery.validationEngine.js');
		wp_register_style('validator.engine.style', 'http://www.position-relative.net/creation/formValidator/css/validationEngine.jquery.css');
		wp_register_script('validator.engine.en.js','https://github.com/posabsolute/jQuery-Validation-Engine/raw/master/js/languages/jquery.validationEngine-en.js');
		
	}
	
	
	public function title_label($title){
		if($this->title_label && get_post_type() == $this->post_type)
			return $this->title_label;
			
		return $title;
	}
	
	public function media_send_to_editor($html, $id, $attachment){
		return json_encode($attachment);
	}
	
	public function manage_edit_columns($columns){
		foreach($this->fields as $name => $value){
			if($value['manage'] && $value['manage']['visible'])
				$columns[$this->post_type.'_'.$name] = $value['label'];
		}

		foreach($this->manage_columns_hidden as $name){
			unset( $columns[$name] );
		}
    	
    	return $columns;
	}
	
	public function manage_custom_column($column) {
		foreach($this->fields as $name => $value){
			if($value['manage'] && $value['manage']['visible']){
				$col_name = $this->post_type.'_'.$name;
				if($col_name == $column){
					$meta_value = get_post_meta( get_the_ID(), $name, true );
					echo esc_html($meta_value);
				}
			}
		}
	}
	
	public function manage_shortable_columns($columns){
		foreach($this->fields as $name => $value){
			if($value['manage'] && $value['manage']['shortable'])
				$columns[$this->post_type.'_'.$name] = $this->post_type.'_'.$name;
		}
    	return $columns;
	}
	
	public function column_orderby($vars) {
	    if ( !is_admin() )
	        return $vars;
	    
	    foreach($this->fields as $name => $value){
			if($value['manage'] && $value['manage']['visible']){
				$col_name = $this->post_type.'_'.$name;
	    		if ( isset( $vars['orderby'] ) && $col_name == $vars['orderby'] ) {
	        		$vars = array_merge( $vars, array( 'meta_key' => $name, 'orderby' => 'meta_value' ) );
	   			 }
			}
		}
	    return $vars;
	}
	
	
	public function restrict_manage_posts(){
		global $wp_query;
		$screen = get_current_screen();
    
		if ( $screen->post_type == $this->post_type ) {    
    		foreach($this->taxonomy_filters as $name =>$tax){
    			$tax_name = $this->post_type.'_'.$name;
		        wp_dropdown_categories( array(
		            'show_option_all' => 'Show All '.$this->name,
		            'taxonomy' => $tax_name,
		            'name' => $tax_name,
		            'orderby' => 'name',
		            'selected' => ( isset( $wp_query->query[$tax_name] ) ? $wp_query->query[$tax_name] : '' ),
		            'hierarchical' => false,
		            'depth' => 3,
		            'show_count' => false,
		            'hide_empty' => true,
		        ) );
		    }    		
    	}

	}
	
	
	public function perform_filtering( $query ) {
	    $q = &$query->query_vars;
	    foreach($this->taxonomy_filters as $name =>$tax){
    		$tax_name = $this->post_type.'_'.$name;
		    if (($q[$tax_name]) && is_numeric($q[$tax_name])) {
		        $term = get_term_by( 'id', $q[$tax_name], $tax_name);
		        $q[$tax_name] = $term->slug;
		    }
	    }
	}
	
	public function template_include($template_path){
		global $wp_query;
				            
		if (get_post_type() == $this->post_type ) {
			$ext = $wp_query->query_vars['ext'];
			$method = strtolower($_SERVER["REQUEST_METHOD"]);
			
			if(is_single()){
	            if($ext == 'content'){
		            if($this->templates['single-content']){
		                return $this->templates['single-content'];
		            }
		            else{
		            	return $template_path;	
		            }
				}
	            
				if(in_array($ext, $this->request_types)){
					$fn = $method.'_single_'.$ext;
					if(method_exists($this, $fn)){
						$this->$fn();
						exit;
					}
				}
				else if($this->templates['single-page']){
		            $template_path = $this->templates['single-page'];
				}
			}
			else{
	            if($ext == 'content'){
		            if($this->templates['archive-content']){
		                return $this->templates['archive-content'];
		            }
		            else{
		            	return $template_path;	
		            }
				}
	            
				if(in_array($ext, $this->request_types)){
					$fn = $method.'_archive_'.$ext;
					if(method_exists($this, $fn)){
						$this->$fn();
						exit;
					}
				}
				else if($this->templates['archive-page']){
		            $template_path = $this->templates['archive-page'];
				}
			}
		}
		return $template_path;
	}
	
	public function register_taxonomy($name, $tax){
		register_taxonomy(
			$this->post_type.'_'.$name,
			$this->post_type,
			array(
				'labels' => array(
					'name' => $tax['name'],
					'add_new_item' => 'Add New '.$tax['name'],
            		'new_item_name' => "New ".$tax['name']
				),
				'rewrite' => array(
					'slug' => strtolower($tax['name'])
				),
				'show_ui' => $tax['show_ui'],
            	'show_tagcloud' => $tax['show_tagcloud'],
            	'hierarchical' => $tax['hierarchical']
			)
		);
		
		if($tax['enable_filter']){
			$this->taxonomy_filters[$name] = $tax;
		}
	}
	
	public function get_metabox($id, $post){
		
		wp_enqueue_style('validator.engine.style');
		wp_enqueue_script('validator.engine.js');
		wp_enqueue_script('validator.engine.en.js');
		
		$fields = $this->metaboxes[$id]['fields'];
		echo '<table>';
		
		foreach($fields as $field_name){
			$field = $this->fields[$field_name];
			$value = esc_html(get_post_meta($post->ID, $field_name, true));
			$name = $this->post_type.'_'.$field_name;
			$method = 'get_'.$field['type'];

		?>
	        <tr>
	            <td style="width: 30%"><?php echo $field['label'] ?></td>
	            <td style="width: 70%">
				<?php
					if(method_exists('PostTypeBuilderField', $method)){
						PostTypeBuilderField::$method($name, $value, $field);
					}
					else{
						PostTypeBuilderField::get_text($name, $value, $field);
					}
				?>
	            </td>
	        </tr>
	        <script>
			     jQuery(document).ready(function($){
		    		$("#post").validationEngine();
				});
	        </script>
    	<?php
		}
    	echo '</table>';
	}
	
	public function on_save($id, $post){
		//$format = get_option('date_format').' '.get_option('time_format').' T';
		//$date = date_create_from_format($format, $_POST['test_type_date']);
		//iw_dump($date->format('U'), $_POST['test_type_date'], $format);
		if($_POST['post_type'] != $this->post_type)
			return;
			
		foreach($this->fields as $field_name => $field){
			$name = $this->post_type.'_'.$field_name;
			if(isset($_POST[$name])){
				update_post_meta($id, $field_name, $_POST[$name]);
			}
		}
	}
	
	public function get_fields($id){
		return get_post_custom($id);
	}
}


class PostTypeBuilderField {
	
	public static function build_validation($field, $custom){
		//
		// docs http://posabsolute.github.com/jQuery-Validation-Engine
		//
		$buffer = 'validate[';
		if($field['required'])
			$rule[] = 'required';
		if($field['min']) $rule[] = "min[{$field['min']}]";
		if($field['max']) $rule[] = "max[{$field['max']}]";
		if($field['minSize']) $rule[] = "minSize[{$field['minSize']}]";
		if($field['maxSize']) $rule[] = "maxSize[{$field['maxSize']}]";
		if($field['creditcard']) $rule[] = "creditCard";
		if($custom)	$rule[] = 'custom['.$custom.']';

		$buffer .= implode(',',$rule).']';
		return $buffer;
	}
	
	public static function get_text($name, $value, $field){
		
		?>
		<input type="text" size="30" class="<?php echo self::build_validation($field); ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	}
	
	public static function get_password($name, $value, $field){
		?>
		<input type="password" class="<?php echo self::build_validation($field); ?>" size="30" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	}
	
	public static function get_email($name, $value, $field){
		
		?>
		<input type="text" class="<?php echo self::build_validation($field, 'email'); ?>" size="30" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	}
	
	public static function get_url($name, $value, $field){
		?>
		<input type="text" class="<?php echo self::build_validation($field, 'url'); ?>" size="30" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	}
	
	public static function get_number($name, $value, $field){
		?>
		<input type="text" class="<?php echo self::build_validation($field, 'number'); ?>" size="30" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php
	}
	
	public static function get_textarea($name, $value, $field){
		?>
		<textarea type="text" name="<?php echo $name; ?>" class="<?php echo self::build_validation($field); ?>">
		<?php echo $value; ?>
		</textarea>
		<?php
	}
	
	public static function get_radio($name, $value, $field){
		foreach($field['options'] as $val => $label){
			$id = 'form_'.$name.'_'.rand();
		?>
		<input class="<?php echo self::build_validation($field); ?>" type="radio" name="<?php echo $name; ?>" id="<?php echo $id; ?>" <?php if($val == $value) echo 'checked'; ?> value="<?php echo $value; ?>"> 
		<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		<?php
		}
	}
	
	public static function get_checkbox($name, $value, $field){
		$id = 'form_'.$name.'_'.rand();
		?>
		<input class="<?php echo self::build_validation($field); ?>" type="checkbox" name="<?php echo $name; ?>" id="<?php echo $id; ?>" <?php if($value) echo 'checked'; ?> value="1"> 
		<label for="<?php echo $id; ?>"><?php echo $field['help']; ?></label>
		<?php
	}
	
	public static function get_checkboxes($name, $values, $field){
		foreach($field['options'] as $val => $label){
			$id = 'form_'.$name.'_'.rand();
		?>
		<input class="<?php echo self::build_validation($field); ?>" type="checkbox" name="<?php echo $name; ?>[]" id="<?php echo $id; ?>" <?php if(in_array($val, $values)) echo 'checked'; ?> value="<?php echo $val; ?>"> 
		<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		<?php
		}
	}
	
	public static function get_select($name, $values, $field){
		?>
		<select class="<?php echo self::build_validation($field); ?>" name="<?php echo $name; ?>" <?php if($field['multiple']) echo 'multiple'; ?>>
		<?php
		foreach($field['options'] as $val => $label){
			$selected = $field['multiple'] ? in_array($val, $values) : ($val == $value);
		?>
		<option <?php if($selected) echo 'selected'; ?> value="<?php echo $val; ?>"> 
			<?php echo $label; ?>
		</option>
		<?php
		}
		?>
		</select>
		<?php
	}
	
	public static function get_date($name, $value, $field){
		self::_datetime($name, $value, 'datepicker', $field);
	}
	
	public static function get_time($name, $value, $field){
		self::_datetime($name, $value, 'timepicker', $field);
	}
	
	public static function get_datetime($name, $value, $field){
		self::_datetime($name, $value, 'datetimepicker', $field);
	}
	
	public static function _datetime($name, $value, $func, $field){
		
		wp_enqueue_style('jquery.ui.theme');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('datetimepicker.style');
		wp_enqueue_script('jquery-ui-slider');
		wp_enqueue_script('datetimepicker.js');
		
		$date_format = get_option('date_format');
		$date_format = str_ireplace('Y', 'y', $date_format);
		$time_format = get_option('time_format');
		$time_format = str_ireplace('i', 'mm', $time_format);
		if($func != 'timepicker')
			$time_format .= ' z';
		
		$command = $func == 'datepicker' ? 'datetimepicker' : $func;
		
		$id = 'rand_'.rand();
		?>
		<input class="<?php echo self::build_validation($field); ?>" type="text" size="30" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<script>
		jQuery(document).ready(function($){
    		$("#<?php echo $id; ?>").<?php echo $command; ?>({
    			showTimezone: true,
    			useLocalTimezone: true,
    			timezoneIso8601: true,
    			addSliderAccess: true,
				sliderAccessArgs: { touchonly: false },
    		<?php if($func == 'datepicker'): ?>
    			showHour: false,
    			showMinute: false,
    			showTime: false,
    		<?php endif; ?>
    			timeFormat: '<?php echo $time_format; ?>',
    			dateFormat: '<?php echo $date_format; ?>'
    		});
		});
		</script>
		<?php
	}
	
	public static function get_media($name, $values, $field){
	    // load js and style depedency
	    wp_enqueue_script('jquery');  
        wp_enqueue_script('thickbox');  
        wp_enqueue_style('thickbox');  
        wp_enqueue_script('media-upload');
        $id = 'rand_'.rand();
		?>
		<ul id="<?php echo $id; ?>-values">
			<?php 
				foreach($values as $value): 
			?>
				<input type="checkbox" value="<?php echo $value; ?>" name="<?php echo $name; ?>[]">
			<?php endforeach; ?>
		</ul>
		<button id="<?php echo $id; ?>" data-name='<?php echo $name; ?>' data-label='<?php echo $field['label']; ?>'>Add Media</button>
		<script>
		jQuery(document).ready(function($) {  
			var $element = $('#<?php echo $id; ?>')
			,	$values_container = $('#<?php echo $id; ?>-values')
			,	label = $element.data('label')
			,	name = $element.data('name');
			
		    $element.click(function() {  
		        tb_show(label, 'media-upload.php?referer='+name+'&type=image&TB_iframe=true&post_id=0', false);  
		        window.send_to_editor = function(html) {  
				    var data = $(html).attr('src'); 
				    $values_container.append(pt_media_render(data)); 
				    tb_remove();  
				} 
		        return false;   
		    });  
		    
		    function pt_media_render(data){
		    	var buffer = ''
		    	,	id = '_' + Math.random(true);
		    	
			    buffer += '<li>';
				buffer += '<label for="'+id+'">';
				buffer += '<img src="'+data+'">';
				buffer += '<input style="position:absolute;left:-20000px;" type="checkbox" value="'+data+'" name="'+name+'[]" id="'+id+'" checked="checked">';
				buffer += '<p>'+data+'</p>';
				buffer += '</label>';
				buffer += '</li>';
				return buffer;
		    }
		    
		    $values_container.find('input[type=checkbox]').each(function(i, $el){
		    	var value = $el.attr('value');
		    	$el.replaceWith(pt_media_render(value));
		    });
		});  
		</script>
		<?php
	}
}

}