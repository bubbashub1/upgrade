<?php
if (!defined('ABSPATH')) exit;
/**
 * BubbaHub 4.1 safety and integrity guards.
 * Prevents double payment starts, leader self-confirmation of bank transfers,
 * mismatched PayPal captures, and over-capacity bookings when a listing exposes a capacity field.
 */
class BubbaHubFinanceSecurity {
  public static function init(){
    add_filter('rest_pre_dispatch',[__CLASS__,'guard'],1,3);
  }

  private static function table($name){global $wpdb;return $wpdb->prefix.'bubbahub_'.$name;}

  public static function guard($result,$server,$request){
    $route=$request->get_route();
    $method=$request->get_method();

    // A leader must never be able to mark their own booking as paid. Bank-transfer
    // receipt is a BubbaHub/admin action because it releases money to the wallet.
    if($method==='POST' && preg_match('#^/bubbahub/v1/leader/bookings/(\d+)$#',$route,$m)){
      $p=$request->get_json_params();
      if(sanitize_key($p['status']??'')==='paid' && !current_user_can('manage_options')){
        return new WP_Error('payment_confirmation_forbidden','Only a BubbaHub administrator can confirm a payment and release funds to a leader wallet.',['status'=>403]);
      }
    }

    // Validate a PayPal capture against the order originally created for this booking.
    if($method==='POST' && preg_match('#^/bubbahub/v1/finance/paypal/(\d+)/capture$#',$route,$m)){
      global $wpdb;
      $booking=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('bookings').' WHERE id=%d AND customer_id=%d',absint($m[1]),get_current_user_id()),ARRAY_A);
      if(!$booking)return new WP_Error('not_found','Booking not found.',['status'=>404]);
      if($booking['status']!=='pending_payment' || $booking['payment_method']!=='paypal')return new WP_Error('payment_state','This booking is not awaiting PayPal payment.',['status'=>409]);
      $p=$request->get_json_params();
      $order=sanitize_text_field($p['order_id']??'');
      if(!$order || empty($booking['provider_ref']) || !hash_equals((string)$booking['provider_ref'],(string)$order))return new WP_Error('paypal_order_mismatch','The PayPal order does not belong to this booking.',['status'=>400]);
    }

    // A paid/cancelled booking cannot be sent through checkout again.
    if($method==='POST' && preg_match('#^/bubbahub/v1/bookings/(\d+)/pay$#',$route,$m)){
      global $wpdb;
      $booking=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('bookings').' WHERE id=%d AND customer_id=%d',absint($m[1]),get_current_user_id()),ARRAY_A);
      if(!$booking)return new WP_Error('not_found','Booking not found.',['status'=>404]);
      if($booking['status']==='paid')return new WP_Error('already_paid','This booking has already been paid.',['status'=>409]);
      if(in_array($booking['status'],['cancelled','attended','no_show'],true))return new WP_Error('booking_closed','This booking is no longer payable.',['status'=>409]);
    }

    // Enforce a listing/session capacity when a common capacity field is present.
    if($method==='POST' && $route==='/bubbahub/v1/bookings'){
      $p=$request->get_json_params();
      $listing=absint($p['listing_id']??0);
      $qty=max(1,absint($p['ticket_quantity']??1));
      if($listing){
        $capacity=self::listing_capacity($listing);
        if($capacity!==null){
          global $wpdb;
          $session=absint($p['session_id']??0);
          $sql='SELECT COALESCE(SUM(ticket_quantity),0) FROM '.self::table('bookings').' WHERE listing_id=%d AND status IN (\'pending_payment\',\'awaiting_bank_transfer\',\'paid\',\'confirmed\')';
          $args=[$listing];
          if($session){$sql.=' AND session_id=%d';$args[]=$session;}
          $booked=(int)$wpdb->get_var($wpdb->prepare($sql,$args));
          if($booked+$qty>$capacity)return new WP_Error('capacity_reached','There are not enough spaces available for this booking.',['status'=>409,'capacity'=>$capacity,'booked'=>$booked,'requested'=>$qty]);
        }
      }
    }
    return $result;
  }

  private static function listing_capacity($listing){
    $keys=['capacity','max_capacity','class_capacity','session_capacity','spaces','places','maximum_places','max_places'];
    foreach($keys as $key){
      $value=get_post_meta($listing,'_bubbahub_'.$key,true);
      if($value===''||$value===null)$value=get_post_meta($listing,$key,true);
      $n=preg_replace('/[^0-9]/','',(string)$value);
      if($n!==''&&is_numeric($n)&&((int)$n)>0)return (int)$n;
    }
    return null;
  }
}
BubbaHubFinanceSecurity::init();
