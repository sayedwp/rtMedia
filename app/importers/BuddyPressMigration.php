<?php

/**
 * Description of BuddyPress_Migration
 *
 * @author faishal
 */
class BuddyPressMigration {

    public $bmp_table = "";

    function __construct($tablename) {
        $this->bmp_table = $tablename;
    }

    function get_total_count() {
        global $wpdb;
        $sql = "select count(*)
                from
                    {$wpdb->postmeta} a
                        left join
                    {$wpdb->postmeta} b ON ((a.post_id = b.post_id)
                        and (b.meta_key = 'bp-media-key'))
                        left join
                    {$wpdb->postmeta} c ON (a.post_id = c.post_id)
                        and (c.meta_key = 'bp_media_child_activity')
                        left join
                    {$wpdb->posts} p ON (a.post_id = p.id)
                where
                    a.post_id > 0
                        and a.meta_key = 'bp_media_privacy'";
        return $wpdb->get_var($sql);
    }

    function get_last_imported() {
        global $wpdb;
        $sql = "select media_id
                from {$this->bmp_table} where blog_id = %d order by media_id desc limit 1 ";
        return $wpdb->get_var($wpdb->prepare($sql, get_current_blog_id()));
    }

    function get_done_count() {
        global $wpdb;
        $sql = "select count(*)
                from {$this->bmp_table} where blog_id = %d";
        return $wpdb->get_var($wpdb->prepare($sql, get_current_blog_id()));
    }

    function migrate_to_new_db($lastid = 0,$limit = 5) {
        if (!$lastid) {
            $lastid = $this->get_last_imported();
            if (!$lastid)
                $lastid = 0;
        }

        global $wpdb;
        $sql = "select 
                    a.post_id as 'post_id',
                    a.meta_value as 'privacy',
                    b.meta_value as 'context_id',
                    c.meta_value as 'activity_id',
                    p.post_type,
                    p.post_mime_type
                    
                from
                    {$wpdb->postmeta} a
                        left join
                    {$wpdb->postmeta} b ON ((a.post_id = b.post_id)
                        and (b.meta_key = 'bp-media-key'))
                        left join
                    {$wpdb->postmeta} c ON (a.post_id = c.post_id)
                        and (c.meta_key = 'bp_media_child_activity')
                        left join
                    {$wpdb->posts} p ON (a.post_id = p.id)
                where
                    a.post_id > %d
                        and a.meta_key = 'bp_media_privacy'
                order by a.post_id
                limit %d";

        $results = $wpdb->get_results ($wpdb->prepare( $sql, $lastid , $limit ) );
        if ($results) {
            $blog_id = get_current_blog_id();
            foreach ($results as $result) {
                $media_id = $result->post_id;

                if ($result->post_type != "attachement") {
                    $media_type = "album";
                    
                } else {
                    $mime_type = strtolower($result->post_mime_type);
                    if (strpos($mime_type, "image") == 0) {
                        $media_type = "image";
                    } else if (strpos($mime_type, "audio") == 0) {
                        $media_type = "audio";
                    } else if (strpos($mime_type, "video") == 0) {
                        $media_type = "video";
                    } else {
                        $media_type = "other";
                    }
                }
                
                if( intval($result->context_id) > 0){
                    $context = "media";
                }else{
                    $context = "group";
                }
                $wpdb->insert(
                        $this->bmp_table, array(
                    'blog_id' => $blog_id,
                    'media_id' => $media_id,
                    'media_type' => $media_type,
                    "context" => $context,
                    "context_id" => abs(intval($result->context_id)),
                    "activity_id" => $result->activity_id,
                    "privacy" => $result->privacy,
                        ), array('%d', '%d', '%s', '%s', '%d', '%d', '%d')
                );
            }
        }
    }
}?>
