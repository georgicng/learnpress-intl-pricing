<?php

/**
 * Plugin load class.
 *
 * @author   Ikpugbu George
 * @package  LearnPress/Intl_Pricing
 * @version  1.0.0
 */

defined('ABSPATH') or die();

if (!class_exists('LP_Addon_Intl_Pricing')) {
    /**
     * Class LP_Addon_Intl_Pricing.
     */
    class LP_Addon_Intl_Pricing extends LP_Addon
    {

        /**
         * @var string
         */
        public $version = LP_ADDON_INTL_PRICING_VER;

        /**
         * @var string
         */
        public $require_version = LP_ADDON_INTL_PRICING_REQUIRE_VER;

        public function __construct()
        {
            parent::__construct();
        }

        /**
         * Init hooks.
         */
        protected function _init_hooks()
        {          
            add_filter('learn-press/general-settings-fields', array($this, 'add_config'));
            add_filter('lp/course/meta-box/fields/price',  array($this, 'add_fields'));
            add_filter('learn-press/currency', array($this, 'set_currency'));
            add_filter('learn-press/course/price', array($this, 'set_price'));
        }

        
        /**
         * Get user IP address.
         *
         * @param null
         *
         * @return string
         */
        protected function get_visitor_ip()
        {
            $client  = @$_SERVER['HTTP_CLIENT_IP'];
            $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
            $remote  = $_SERVER['REMOTE_ADDR'];

            if (filter_var($client, FILTER_VALIDATE_IP)) {
                $ip = $client;
            } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
                $ip = $forward;
            } else {
                $ip = $remote;
            }

            return $ip;
        }

        /**
         * Resolve user IP to country  using geoplugin api.
         *
         * @param null
         *
         * @return string
         */
        protected function get_visitor_country()
        {
            $ip = $this->get_visitor_ip();

            if ($value = get_transient($ip)) {
                return $value;
            }

            $country  = "Unknown";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://www.geoplugin.net/json.gp?ip=" . $ip);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $ip_data_in = curl_exec($ch); // string
            curl_close($ch);

            $ip_data = json_decode($ip_data_in, true);
            $ip_data = str_replace('&quot;', '"', $ip_data); // for PHP 5.2 see stackoverflow.com/questions/3110487/

            if ($ip_data && $ip_data['geoplugin_countryName'] != null) {
                $country = $ip_data['geoplugin_countryName'];
                set_transient($ip, $country, 60 * 60 * 12);
            }

            return $country;
        }

        /**
         * Check if user is from other region.
         *
         * @param null
         *
         * @return boolean
         */
        protected function is_other_region()
        {
            $country = $this->get_visitor_country();
            //TODO: fetch home country from wordpress config
            return !in_array($country, ['Nigeria']) ? true : false;
        }


        /**
         * Add inlt config settings.
         *
         * @param $array
         *
         * @return array
         */
        public function add_config($fields)
        {
            function ids($n)
            {
                if (isset($n['id'])) {
                    return $n['id'];
                }
                return '';
            }
            $index = array_search('currency', array_map('ids', $fields));
            if (!empty($index)) {
                $intl_currency_field = $fields[$index];
                $intl_currency_field['id'] = 'currency_intl';
                $intl_currency_field['title'] = esc_html__('International Currency', 'learnpress');
                $intl_pricing_field = array(
                    'title'    => esc_html__('Enable international pricing', 'learnpress'),
                    'id'       => 'intl_pricing',
                    'default'  => 'no',
                    'type'     => 'checkbox',
                    'desc'     => __('Enable to use a different pricing and currency for outside regions', 'learnpress'),
                );
                array_splice($fields, ($index + 1), 0, [$intl_currency_field, $intl_pricing_field]);
            }
            return $fields;
        }

        /**
         * Add intl pricing fields to metabox if intl pricing is enabled.
         *
         * @param $array
         *
         * @return array
         */
        public function add_fields($fields)
        {
            //TODO: sort fields
            if (LP_Settings::get_option('intl_pricing', 'no') !== 'yes') {
                return $fields;
            }
            global $post;
            $post_id = $post->ID;
            $intl_price = get_post_meta($post_id, '_lp_intl_price', true);
            $intl_sale_price    = get_post_meta($post_id, '_lp_intl_sale_price', true);
            $fields['_lp_intl_price'] = new LP_Meta_Box_Text_Field(
                esc_html__('Intl price', 'learnpress'),
                sprintf(__('Set intl price (<strong>%s</strong>). Leave it blank for <strong>Free</strong>.', 'learnpress'), LP_Settings::instance()->get('currency_intl', 'USD')),
                $intl_price,
                array(
                    'type_input'        => 'text',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '0.01',
                    ),
                    'style'             => 'width: 70px;',
                    'class'             => 'lp_meta_box_intl_price',
                )
            );
            $fields['_lp_intl_sale_price'] = new LP_Meta_Box_Text_Field(
                esc_html__('Intl Sale price', 'learnpress'),
                '',
                $intl_sale_price,
                array(
                    'type_input'        => 'text',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '0.01',
                    ),
                    'style'             => 'width: 70px;',
                    'class'             => 'lp_meta_box_intl_sale_price',
                )
            );
            return $fields;
        }

        /**
         * Use intl price currency if user is from a foreign region.
         *
         * @param $methods
         *
         * @return mixed
         */
        public function set_currency($currency)
        {
            if (is_admin()) {
                return $currency;
            }

            if (LP_Settings::get_option('intl_pricing', 'no') === 'yes' && $this->is_other_region()) {
                return LP_Settings::instance()->get('currency_intl', 'USD');
            }
            return $currency;
        }


        /**
         * Use intl price if set and user is from a foreign region.
         *
         * @param $price
         *
         * @return float
         */
        public function set_price($price)
        {
            if (is_admin()) {
                return $price;
            }

            if (LP_Settings::get_option('intl_pricing', 'no') === 'yes' && $this->is_other_region()) {
                global $post;
                $id = $post->ID;
                $intl_price = floatval(get_post_meta($id, '_lp_intl_price', true));
                $intl_sale_price = floatval(get_post_meta($id, '_lp_intl_sale_price', true));
                $start_date = get_post_meta($id, '_lp_sale_start', true);
                $end_date = get_post_meta($id, '_lp_sale_end', true);
                if ($intl_sale_price < $intl_price && '' !== $start_date && '' !== $end_date) {
                    $now   = time();
                    $end   = strtotime($end_date);
                    $start = strtotime($start_date);

                    if ($now >= $start && $now <= $end) {
                        return $intl_sale_price;
                    }
                }
                return $intl_price;
            }
            return $price;
        }
    }
}
