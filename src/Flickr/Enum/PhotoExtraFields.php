<?php
declare(strict_types=1);

namespace App\Flickr\Enum;

/**
 * When adding new ones also see PhotoMetadata
 *
 * All parameters found through googling: can_addmeta,can_comment,can_download,can_print,can_share,contact,content_type,count_comments,count_faves,count_views,date_taken,date_upload,description,icon_urls_deep,isfavorite,ispro,license,media,needs_interstitial,owner_name,owner_datecreate,path_alias,perm_print,realname,rotation,safety_level,secret_k,secret_h,url_sq,url_q,url_t,url_s,url_n,url_w,url_m,url_z,url_c,url_l,url_h,url_k,url_3k,url_4k,url_f,url_5k,url_6k,url_o,visibility,visibility_source,o_dims,publiceditability,system_moderation,datecreate,date_activity,eighteenplus,invitation_only,needs_interstitial,non_members_privacy,pool_pending_count,privacy,member_pending_count,icon_urls,date_activity_detail,muted,url_sq,url_q,url_t,url_s,url_n,url_w,url_m,url_z,url_c,url_l,url_h,url_k,url_3k,url_4k,url_5k,url_6k,url_o,path_alias,owner_name,can_comment,can_print,count_comments,count_faves,description,isfavorite,license,media,needs_interstitial,owner_name,path_alias,realname,rotation,url_sq,url_q,url_t,url_s,url_n,url_w,url_m,url_z,url_c,url_l,owner_name,path_alias,realname,sizes,url_sq,url_q,url_t,url_s,url_n,url_w,url_m,url_z,url_c,url_l,url_h,url_k,url_3k,url_4k,url_5k,url_6k,needs_interstitial,icon_urls,safe_search,galleries_view_layout_pref
 */
enum PhotoExtraFields: string
{
    case DESCRIPTION     = 'description'; //only valid on some endpoints but not others (?)
    case LICENSE         = 'license';
    case DATE_UPLOAD     = 'date_upload';
    case DATE_TAKEN      = 'date_taken';
    case OWNER_NAME      = 'owner_name';
    case ICON_SERVER     = 'icon_server';
    case ORIGINAL_FORMAT = 'original_format';
    case LAST_UPDATE     = 'last_update';
    case GEO             = 'geo';
    case TAGS            = 'tags';
    case MACHINE_TAGS    = 'machine_tags';
    case ORG_DIMENSIONS  = 'o_dims';
    case VIEWS           = 'views';
    case MEDIA           = 'media';
    case PATH_ALIAS      = 'path_alias';

    //Undocumented ones
    case FAVES_COUNT     = 'count_faves';
    case COMMENTS_COUNT  = 'count_comments';
    //seems to only work in faves(?); adding this magic parameter causes API to return even unsafe photos without OAuth
    //Google doesn't know anything about this one. It doesn't work for album getPhotos.
    case MAGIC_INTERSTITIAL = 'needs_interstitial';
    case SIZES           = 'sizes'; //returns comma-separated list of sizes... that seems ordered too? ;D
    case SAFETY_LEVEL    = 'safety_level';



    //This list should mimic PhotoSize EXACTLY but with "url_" prefix. Some of these are undocumented.
    case URL_SQUARE_75      = 'url_sq';
    case URL_THUMB_75       = 'url_s';
    case URL_THUMB_100      = 'url_t';
    case URL_THUMB_150      = 'url_q';
    case URL_SMALL_240      = 'url_m';
    case URL_SMALL_320      = 'url_n';
    case URL_SMALL_400      = 'url_w';
    case URL_MEDIUM_500     = 'url_';
    case URL_MEDIUM_640     = 'url_z';
    case URL_MEDIUM_800     = 'url_c';
    case URL_LARGE_1024     = 'url_b';
    case URL_LARGE_1600     = 'url_h';
    case URL_LARGE_2048     = 'url_k'; //undocumented https://www.flickr.com/groups/51035612836@N01/discuss/72157636063789543/72157644356066041
    case URL_XLARGE_3K      = 'url_3k';
    case URL_XLARGE_4K      = 'url_4k';
    case URL_XLARGE_4K_2to1 = 'url_f';
    case URL_XLARGE_5K      = 'url_5k';
    case URL_XLARGE_6K      = 'url_6k';
    case URL_ORIGINAL       = 'url_o';

    static public function casesSizes(): array
    {
        $ret = [];
        foreach (PhotoSize::cases() as $case) {
            $ret[] = self::from('url_' . $case->value);
        }

        return $ret;
    }
}
