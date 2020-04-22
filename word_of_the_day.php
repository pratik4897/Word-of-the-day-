<?php
/*
 * Plugin Name:  Word Of The Day
 * Description:  word of the day plugin is used to update daily with new word with meaning of the word in sidebar.this plugin is used to marketinig your blog or website. macking your website or blog updated with this plugin.
 * Version:      1.0
 * Author:       Pratik Bharodiya
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  word-of-the-day
*/

require_once 'Widget/Wotd_simplebanner.php';
if ( class_exists( 'Spreadsheet_Excel_Reader' ) ){
    require_once 'excel_reader2.php';
}
define('WOTD_PLUGIN_DIR', plugin_dir_path(__FILE__));

//load css and jquery in admin side
function wotd_stylesheet() 
{   
    wp_enqueue_style( 'wotd_custom_css', plugins_url( '/css/custom.css', __FILE__ ) );
    wp_enqueue_script( 'wotd_custom_js', plugins_url( '/js/custom.js', __FILE__ ),array('jquery'));
    wp_register_script( 'jQuery', '', null, null, true );
    wp_enqueue_script('jQuery');
    wp_localize_script( 'wotd_custom_js', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
}
add_action('admin_print_styles', 'wotd_stylesheet');

//create table for store the data of the excel sheet.
register_activation_hook( __FILE__, 'wotd_create_db' );
function wotd_create_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'word_collection';
    $sql = "CREATE TABLE $table_name (
        word_id int(9) NOT NULL AUTO_INCREMENT,
        entery_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        word varchar(100) NOT NULL,
        word_type varchar(100) NOT NULL,
        pronounication varchar(100) NOT NULL,
        meaning varchar(100) NOT NULL,
        UNIQUE KEY id (word_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

//remove table from the database after uninstall the plugin
register_deactivation_hook(__FILE__, 'wotd_deactive_table');
function wotd_deactive_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'word_collection';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
} 

//file uploading using jquery/ajax
function file_upload() {
    require_once 'Classes/PHPExcel.php';
    $responce = array();
    $action = sanitize_text_field($_POST['action']);
    $file = sanitize_file_name($_FILES['file']['name']);
    $remove_these = array(' ','`','"','\'','\\','/','%');
    $newFileName = str_replace($remove_these, '', $file);
    $newFileName = time().'-'.$newFileName; 

    $uploadDirectory = plugin_dir_path( __FILE__ ) ."upload/";
    $uploadPath = $uploadDirectory . basename($newFileName);
    
    if(move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {  
        $responce['status'] = '1';
        $responce['message'] = "File upload sucessfully";
    } else{
        $responce['status'] = '0';
        $responce['message'] = "Sorry, file not uploaded, please try again!";  
    }
    $excel_path = sanitize_text_field(plugin_dir_path(__FILE__) . "upload/".basename($newFileName));
    try {
        $inputFileType = PHPExcel_IOFactory::identify($excel_path);
        $excelReader = PHPExcel_IOFactory::createReaderForFile($excel_path);
        $excelObject = $excelReader->load($excel_path);
    } catch (Exception $ex) {
        die('Error loading file"' . pathinfo($excel_path, PATHINFO_BASENAME) . '": ' . $ex->getMessage());
    }

    $sheet = $excelObject->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $rows = $sheet->rangetoArray('A1:'.$highestColumn . $highestRow, "", False, True, False);
    foreach ($rows as $row => $cols) {
        $row_data_array = array();
        foreach($cols as $col => $cell){
            array_push($row_data_array, $cell);
        }

        $current_row = array(
            'entery_date'   => date('Y-m-d', strtotime($row_data_array[0])),
            'word'     => $row_data_array[1],
            'word_type'     => $row_data_array[2],
            'pronounication'     => $row_data_array[3],
            'meaning'     => $row_data_array[4],
        );

        global $wpdb;
        $table_name = $wpdb->prefix . 'word_collection';
        $word_id = $wpdb->get_results("SELECT word_id FROM wp_word_collection WHERE entery_date = '".date('Y-m-d H:i:s', strtotime($row_data_array[0]))."'");
        if($word_id[0]->word_id == ''){
            $wpdb->insert($table_name, $current_row);
        }
    }
    echo json_encode($responce);
}
add_action( 'wp_ajax_file_upload', 'file_upload' );

// Register the widget
function load_all_widget() {
	register_widget( 'wotd_simplebanner' ); 
}
add_action( 'widgets_init', 'load_all_widget' );

//add option page
function wotd_desing_option()  
{  
    add_options_page('Word of the day', 'Word of the day', 'manage_options', 'wotd-design','wotd_manage');  
}
add_action('admin_menu', 'wotd_desing_option');

//page callback function of the page
function wotd_manage(){
    ?>
    <div class="wrap">
        <h2>Word Of The Setting</h2><span>Powered By Aristo Infotech Soluton || Version - 1.0.0</span><hr>
        <form method="post" action="options.php">
            <span>upload your well formated excel sheet of collection of word</span>
            <?php wp_nonce_field('update-options') ?>
            <table class="form-table" role="presentation">
            	<tbody>
            		<tr>
                        <th scope="row">
                            <label for="imageopacity">Upload Excel Sheet</label>
                        </th>
                        <td>
                            <input type="file" id="colletion_of_word" name="colletion_of_word" accept=".xls,.xlsx"  />
                            <p id="file_info" style="color: red;"><span>File Size</span><span id="file_size"style="margin-left:10px;">1.12Kb</span></p>
                    </tr>
            		<tr>
            			<th scope="row">
            				<p><input class="button button-primary" type="submit" name="Submit" value="save changes" /></p>
            			</th>
            		</tr>
            	</tbody>
            </table>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="twitterid" />
        </form>
        <hr>
    </div>
	<?php
}