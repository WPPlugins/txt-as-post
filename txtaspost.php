<?php

/* 
 * Plugin Name:   TXTAsPost
 * Version:       1.2
 * Plugin URI:    http://www.change-my-life.com
 * Description:   Import .txt as post in bulk
 * Author:        Jesse
 * Author URI:    http://www.change-my-life.com
 */

require(dirname(__FILE__) . "/dUnzip2.inc.php");

class TXTAsPost {


		var $log='';


function init(){

add_action('admin_menu', array(&$this,'txtaspost_menu_setup'));

}

function txtaspost_menu_setup() {
   add_options_page('TxtAsPost Settings', 'TXT As Post', 10, __FILE__, array(&$this,'txtaspost_menu'));

   
 if (isset($_FILES["zip"])) {
      if (is_uploaded_file($_FILES['zip']['tmp_name'])) {
        $this->txtaspost_process();
      }
   }
 
}

	

function getLatestTime() {
	global $wpdb;
		
	
	$post = $wpdb->get_results( "SELECT post_date FROM $wpdb->posts ORDER BY post_date DESC limit 0,1" );
	$tm=strtotime($post[0]->post_date);
	
	
	// strtotime will return false if no post found
	if(false == $tm)
		$tm = time();
	return $tm;
	       }


function txtaspost_process() {
   
   //I need much time xD
   set_time_limit(0);
	
   $zipfilename = $_FILES['zip']['tmp_name'];
   $zip = new dUnzip2($zipfilename);
   $zip->debug = false;
   $list = $zip->getList(); 
   $i=absint($_POST['timeInterval']);
   $category= $this->process_category($_POST['category']);
   $post_type= $_POST['post_type'];


   $tags= $_POST['tags'];

   $tm = ($_POST['when']=='Now')?  time() : ( $this->getLatestTime() + $i*3600 );

    

      foreach($list as $filename => $b) {

      	$contents = $zip->unzip($filename);

 		// Process each article
       	if($pid=$this->txtaspost_post($contents,$category,$tm,$tags,$post_type)){
		
		      // time will be associated with the time of latest post
		      $tm += strtotime($pid->post_date) + $i*3600;
	}
	else{
		
		$this->log .= "Fail to post $filename <br />";		
		
	}

    }
	
	
   
      

  
}

function txtaspost_post($contents,$category,$time,$tags,$post_type='post') {
   global $wpdb;

   $contents=str_replace("\n",'NEWLINE',$contents);

   //remove non-ascii
   $contents=preg_replace('/[\x0-\x1F|\x7F-\xFF]/','',$contents);

   $contents=str_replace('NEWLINE',"\n",$contents);

   $post = explode("\n",$contents);
   
   $post_title = $wpdb->escape(trim($post[0]));
	//cool
   $post_content = $wpdb->escape(trim( implode ( "\n" , array_slice( $post,1 ) ) ));



   
   $post_name = sanitize_title( $post_title);
   if (empty($post_title)||empty($post_content)) { return false; }

   // Avoid duplicated post
   if ($wpdb->get_row("SELECT ID FROM {$wpdb->posts} WHERE post_title = '$post_title'")) {
     		return false;
   }

   $pid = wp_insert_post(array(
      "post_title" => $post_title ,
      "post_content" => $post_content,
      "post_name" => $post_name,	
      "post_category" => $category,
      "tags_input" => $tags,
      "post_status" => "publish",			
      "post_author" => 1,
      "post_type" => $post_type,
      "post_date" => date("Y-m-d H:i:s", $time)
   ) );

   return $pid;
}

function process_category($category){

	$cats=explode(",",$category);
	$cats_id=array();
	foreach($cats as $cat){
	    	if($cat){	
			if( 0 == get_cat_id($cat) ){

				if(!function_exists('wp_create_category')) include_once(ABSPATH.'wp-admin/includes/taxonomy.php');

				// 0 means default category, if wp_create_category fail, return 0. nice. xD
				array_push($cats_id,wp_create_category($cat));
			}else
				array_push($cats_id,get_cat_id($cat));
		}//who cares if cat name is empty?
	}
	return array_unique($cats_id);

}

function txtaspost_menu() {
 
   ?>
   <div class="wrap">
      <h2>TXT As post</h2>

      <p>Upload zip file that contains plain text(.txt format files) <br /><span style="color:red;">first line must be TITLE and the rest will be treated as CONTENT.</span></p>

    
      <form method="post" action="" enctype="multipart/form-data">
      <?php wp_nonce_field('update-options'); ?>
      <input type="hidden" name="action" value="update" />

      <p>
         Upload Zip: <input type="file" name="zip" /> &nbsp;Maximum file size <?php echo ini_get('upload_max_filesize');?>
      </p>
      

      <p>
       Time Interval by hours:<input type="text" name="timeInterval" value="8"/>
      </p>
     
    
      <p>
	Category/Categories to post(separated by comma):<input type="text" id="category" name="category" value=""/>
	 <p>
	Hint: Category will automatically added if not exists. 
        </p>
      </p>
       
      <p>
       Tags(separated by comma):<input type="text" name="tags" id="tags" value=""/>
      </p>
	
<script   language="javascript">  
  function   a(s)  
  {	
	if(s=='page'){
        document.getElementById('category').value='';
        document.getElementById('category').setAttribute("readonly","readonly");
	document.getElementById('tags').value='';
        document.getElementById('tags').setAttribute("readonly","readonly");
	}else{
		document.getElementById('category').removeAttribute("readonly");
		document.getElementById('tags').removeAttribute("readonly");
	}
	

  }  
  </script>
      <p>
         Post type: <select id="post_type" name="post_type">
			<option value="post" onclick="a(this.value);" selected="selected">post</option>
			<option value="page" onclick="a(this.value);"			>page</option>
		    </select>
      </p>
     <p>
      Published time<br />
                  <input type="radio" name="when"  value="Next" checked>According to the time of latest post<br />
                  <input type="radio" name="when"  value="Now"       >From Now On<br />

      </p>
     <p>
	Hint: the post date will be current time, if no post found.
     </p>
      <p><input type="submit" class="button" value="Upload" /></p>
      Error log:<br/>
	<div id="log" style="color:red;"><?php echo $this->log;?></div>
  </form>
   </div>
   <?php
}


}
$txtaspost = & new TXTAsPost();
$txtaspost->init();



?>
