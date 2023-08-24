<?php
class Link_Building
{

    public function __construct()
    {
        $this->settings = get_option('lbqueue_settings_page');
        $this->ReportFolderDomainID = isset($this->settings['ReportFolderDomainID']) ? $this->settings['ReportFolderDomainID'] : '1Lg1_V3NL5C9s8VS0d-m3pcIqUYMFcLck';
        $this->ReportFolderPostID = isset($this->settings['ReportFolderPostID']) ? $this->settings['ReportFolderPostID'] : '1ANfxiRoIudPRlMIouk6wCFpiDWgu_dWK';
        $this->DomainSpreadsheetId = isset($this->settings['DomainSpreadsheetId']) ? $this->settings['DomainSpreadsheetId'] : '1xrcHOQrLZQF2_wMOujXiXw2txip_XX501N3kwqPrEH0';
        $this->DomainSpreadsheetName = isset($this->settings['DomainSpreadsheetName']) ? $this->settings['DomainSpreadsheetName'] : 'Auto';
        $this->gg = new LB_GG();

        add_action('admin_enqueue_scripts', array($this,'lbc_backend_js'));

        add_action('wp_ajax_lb_test', array($this, 'lb_test'));
        add_action('wp_ajax_nopriv_lb_test', array($this, 'lb_test'));

        add_action('wp_ajax_lb_run', array($this, 'lb_run'));
        add_action('wp_ajax_nopriv_lb_run', array($this, 'lb_run'));
    }
    public function lbc_backend_js()
    {
        wp_enqueue_script('lbc-backend-js', NCOL_LB_URL . 'js/lbc_admin.js', array('jquery'), strtotime('now'), false);
        wp_localize_script(
            'lbc-backend-js',
            'lbc',
            array(
                'ajax' => admin_url('admin-ajax.php'),
                'lblink_nonce' => wp_create_nonce('lbc_secure'),
                'run_queue' => 'lb_run',
            )
        );
    }
    public function get_random_site($num=1){
        try {
            $values = $this->gg->get_site($this->DomainSpreadsheetId, $this->DomainSpreadsheetName);
            if( !empty($values) ){
                $originalvalues = $values;
                array_shift($values);
                shuffle($values);
                $randomRows = array_slice($values, 0, $num);
                if ($randomRows) {
                    foreach ($randomRows as $key => $row) {
                        $rowIndex = array_search($row, $originalvalues);
                        if (!isset($row[7]) || $row[7] == "") {
                            $row[7] = $this->create_domain_report_file($row,$rowIndex);
                            $row[8] = 'https://docs.google.com/spreadsheets/d/'.$row[7].'/edit';
                        }
                        $randomRows[$key] = $row;
                    }
                    return $randomRows;
                } else {
                    return "";
                }
            }else{
                $this->gg->clearColumn($this->DomainSpreadsheetId,$this->DomainSpreadsheetName,9);
                return "";
            }
        } catch (Exception $e) {
            return 'Lỗi: ' . $e->getMessage();
        }
    }
    public function create_domain_report_file($row,$rowIndex){
        $reportfileID = $this->gg->createGoogleSheetInFolder($row[1],$this->ReportFolderDomainID);
        $reportRange = $this->DomainSpreadsheetName . "!H" . ($rowIndex+1);
        $this->gg->updateRange($this->DomainSpreadsheetId, $reportRange, $reportfileID);
        return $reportfileID;
    }
    public function lb_test()
    {
        $domains = $this->get_random_site();
        print_r($domains);
        wp_die();
    }
    public function lb_run()
    {
        try {
            $args = array(
                'post_type' => 'lbqueue',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'meta_query' => array(
                    'relation' => 'AND', // Xác định rằng cả hai điều kiện phải đúng
                    array(
                        'key' => 'lb_allow_to_run',
                        'value' => '1',
                        'compare' => '=',
                    ),
                    array(
                        'relation' => 'OR', // Xác định rằng một trong hai điều kiện phải đúng
                        array(
                            'key' => 'lb_total_link',
                            'value' => 50,
                            'compare' => '<',
                            'type' => 'NUMERIC'
                        ),
                        array(
                            'key' => 'lb_total_link',
                            'compare' => 'NOT EXISTS' // Kiểm tra trường hợp không tồn tại
                        )
                    )
                )
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    global $post;
                    $lb_button = get_post_meta($post->ID,'lb_button',true);
                    $lb_sources = get_post_meta($post->ID,'lb_sources',true);
                    $lb_num_link = get_post_meta($post->ID,'lb_num_link',true);
                    $lb_button = get_post_meta($post->ID,'lb_button',true);
                    $reportsheetID = get_post_meta($post->ID,'report_sheet_id',true);
                    $list_titles = get_post_meta($post->ID,'lb_titles',true);
                    shuffle($list_titles);
                    $title_random = $list_titles[0]['title'];
                    $content = get_the_content();
                    if( isset($lb_sources) ){
                        shuffle($lb_sources);
                        $content .= '<p>Nguồn tham khảo: <a href="'.$lb_sources[0]['source'].'" target="_blank">'.$lb_sources[0]['source'].'</a></p>';
                    }
                    if( isset($lb_button[0]["url"]) && $lb_button[0]["url"] != "" ){
                        $content .= '<p style="text-align: center;"><a href="'.$lb_button[0]["url"].'" style="display: inline-block; background-color: #007bff; color: #ffffff; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none;">'.$lb_button[0]["text"].'</a></p>';
                    }
                    $queue_id = get_the_ID();
                    $title = get_the_title();
                    $count_report_link = 0;
                    if( $reportsheetID == "" ){
                        $reportsheetID = $this->gg->createGoogleSheetInFolder($title, $this->ReportFolderPostID);
                        add_post_meta($post->ID,'report_sheet_id',$reportsheetID);
                        add_post_meta($post->ID,'report_sheet_url','https://docs.google.com/spreadsheets/d/'.$reportsheetID.'/edit');
                    }else{
                        $count_report_link = $this->gg->countRowsInSheetByIndex($reportsheetID, 0);
                        update_post_meta($post->ID,'report_sheet_url','https://docs.google.com/spreadsheets/d/'.$reportsheetID.'/edit');
                    }
                }
                if( $count_report_link < $lb_num_link ){

                    if( $title_random != "" ){
                        $post_data = array(
                            'title'   => $title_random,
                            'content' => do_shortcode($content),
                            'status'  => 'publish',
                        );
                        $domains = $this->get_random_site();
                        if( isset($domains[0][2]) ){
                            $domain = $domains[0][2];
                            $login = $domains[0][3];
                            $password = $domains[0][4];

                            if(isset($domains[0][5])){
                                $post_data['categories'] = array($domains[0][5]);
                            }

                            $body = $this->post_by_rest_api($domain, $login, $password, $post_data);
                            $domain_stt = "ok";
                            if ( isset($body) ) {
                                $url = isset($body->link) ? $body->link : (isset($body->guid->rendered) ? $body->guid->rendered : (isset($body->guid->raw) ? $body->guid->raw : ''));
                                if (!empty($url)) {
                                    $data = array($body->id,$url,$queue_id);
                                    $this->gg->addDataToSheetByNumber($domains[0][7], 1, $data);
                                    $this->gg->addDataToSheetByNumber($reportsheetID, 1, $data);
                                }elseif(isset($body->code)){
                                    $domain_stt = $body->code;
                                }else{
                                    $domain_stt = "no_response";
                                }
                            }else{
                                $domain_stt = "no_body_response";
                            }
                            $domain_row = $this->gg->findRowByValue($this->DomainSpreadsheetId, $this->DomainSpreadsheetName, $domains[0][1], 1);
                            $domain_row_range_j = $this->DomainSpreadsheetName . "!J" . key($domain_row);
                            $domain_row_range_g = $this->DomainSpreadsheetName . "!G" . key($domain_row);
                            $this->gg->updateRange($this->DomainSpreadsheetId,$domain_row_range_j,"x");
                            $this->gg->updateRange($this->DomainSpreadsheetId,$domain_row_range_g, $domain_stt);
                            wp_send_json_success(array('code'=>'ok','text'=>$title . " đã đăng " . ($count_report_link + 1) . " link"));
                        }
                    }else{
                        wp_send_json_success(array('code'=>'no_title','text'=>"Không có tiêu đề để đăng bài"));
                    }
                }else{
                    update_post_meta($queue_id,'lb_allow_to_run',0);
                    update_post_meta($queue_id,'lb_num_report',$lb_num_link);
                    wp_send_json_success( array('code'=>'full','text'=>$title . " đã đăng đủ " . $lb_num_link . " link") );
                }
                wp_reset_postdata(); // Đặt lại dữ liệu bài viết
            } else {
                wp_send_json_success(array('code'=>'no_found_post','text'=>"Không tìm thấy bài viết phù hợp."));
            }
        } catch (Exception $e) {
            wp_send_json_success('Lỗi: ' . $e->getMessage());
        }
        wp_die();
    }
    private function post_by_rest_api($domain, $login, $password, $post_data){
        $url = $domain.'wp-json/wp/v2/posts';
        $args = array(
            'timeout'     => 60,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$login:$password")
            ),
            'body' => $post_data
        );
        $response = wp_remote_post($url, $args);
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body)) {
            return $body;
        }
        return false;
    }
}

$link_building = new Link_Building();