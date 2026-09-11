<?php
if (!defined('ABSPATH')) exit;
/** Safe public bootstrap for the directory. Includes listing meta so the frontend can render age, price, term-time, location and coordinates. */
add_filter('rest_pre_dispatch', function($result, $server, $request){
    if ($result !== null || !($request instanceof WP_REST_Request) || $request->get_route() !== '/bubbahub/v1/bootstrap') return $result;
    $make_posts = function($type, $limit) {
        $posts = get_posts(['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC']);
        $out=[];
        foreach($posts as $p){
            $terms=[];
            foreach((array)get_object_taxonomies($p->post_type) as $tax){$names=wp_get_object_terms($p->ID,$tax,['fields'=>'names']);if(!is_wp_error($names))$terms=array_merge($terms,$names);}
            $meta=[];
            foreach((array)get_post_meta($p->ID) as $key=>$values){
                if(str_starts_with((string)$key,'_')) continue;
                if(preg_match('/pass|token|secret|password|api.?key/i',(string)$key)) continue;
                $meta[$key]=count($values)===1?$values[0]:$values;
            }
            $out[]=['id'=>(int)$p->ID,'title'=>get_the_title($p),'slug'=>$p->post_name,'content'=>apply_filters('the_content',$p->post_content),'excerpt'=>get_the_excerpt($p),'image'=>get_the_post_thumbnail_url($p,'large'),'terms'=>array_values(array_unique($terms)),'custom_fields'=>$meta,'link'=>get_permalink($p->ID),'date'=>get_post_time('c',true,$p),'modified'=>get_post_modified_time('c',true,$p)];
        }
        return $out;
    };
    return new WP_REST_Response(['site'=>['name'=>get_bloginfo('name'),'url'=>home_url()],'groups'=>$make_posts('bh_group',12),'events'=>$make_posts('bh_event',12),'apps'=>$make_posts('bh_app',12),'specialists'=>$make_posts('bh_specialist',12),'articles'=>$make_posts('bh_article',12)],200);
},10,3);
