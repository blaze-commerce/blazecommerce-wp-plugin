<?php

namespace BlazeWooless\Settings;

class BaseSettings {
    public $option_key;
    public $page_label;
    public $tab_key;

    public function __construct( $option_key )
    {
        $this->option_key = $option_key;

        $this->register_hooks();
        
        add_action( 'admin_init', array( $this, 'init' ), 10, 1 );
        add_action( 'blaze_wooless_settings_navtab', array( $this, 'register_settings_navtab' ), 10, 1 );
        add_action( 'blaze_wooless_render_settings_tab', array( $this, 'render_settings_tab' ), 10, 1 );
        add_action( 'blaze_wooless_render_settings_tab_footer', array( $this, 'render_settings_footer_tab' ), 10, 1 );
    }

    public function init()
    {
        if( false == get_option( $this->option_key ) ) {	
            add_option( $this->option_key );
        }

        foreach ( $this->settings() as $section_key => $section ) {
            add_settings_section(
                $section_key,
                $section['label'],
                null,
                $this->option_key,
            );

            foreach ($section['options'] as $setting) {
                add_settings_field(	
                    $setting['id'],
                    $setting['label'],
                    array( $this, 'field_callback_' . $setting['type'] ),
                    $this->option_key,
                    $section_key,
                    array_merge(
                        $setting['args'],
                        array(
                            'id' => $setting['id'],
                        ),
                    ),
                );
            }
        }

        register_setting(
            $this->option_key,
            $this->option_key,
            array( $this, 'settings_callback' ),
        );
    }

    public function render_display()
    {
        settings_fields( $this->option_key ); 
        do_settings_sections( $this->option_key ); 
    }

    public function get_option( $field_id = false, $default = false )
    {
        $options = get_option( $this->option_key, $default );

        if ($options === "") {
            $options = $default;
        }

        if ( ! $field_id ) return $options;

        return $options[ $field_id ] ?? null;
    }

    public function section_callback() {}
    public function settings_callback( $input ) { return $input; }
    public function register_hooks() {}

    public function settings()
    {
        return array();
    }

    public function field_callback_checkbox( $args ) {
        $value = $this->get_option( $args['id'] );
        $html = '<input type="checkbox" id="'. $args['id'] .'" name="' . $this->option_key . '['. $args['id'] .']" value="1" ' . checked(1, $value, false) . '/>'; 
        $html .= $this->render_field_description( $args, true ); 
        echo $html;
    }

    public function field_callback_text( $args ) {
        $value = $this->get_option( $args['id'] );
        $html = '<input type="text" id="'. $args['id'] .'" name="' . $this->option_key . '['. $args['id'] .']" value="' . sanitize_text_field( $value ). '" />'; 
        $html .= $this->render_field_description( $args ); 
        echo $html;
    }

    public function field_callback_textarea( $args ) {
        $value = $this->get_option( $args['id'] );
        $html = '<textarea rows="4" cols="50" id="'. $args['id'] .'" name="' . $this->option_key . '['. $args['id'] .']">' . sanitize_text_field( $value ). '</textarea>'; 
        $html .= $this->render_field_description( $args ); 
        echo $html;
    }

    public function field_callback_password( $args ) {
        $value = $this->get_option( $args['id'] );
        $html = '<input type="password" id="'. $args['id'] .'" name="' . $this->option_key . '['. $args['id'] .']" value="' . sanitize_text_field( $value ). '" />';
        $html .= $this->render_field_description( $args ); 
        echo $html;
    }

    public function field_callback_select( $args ) {
        $value = $this->get_option( $args['id'] );
        $html = '<select name="' . $this->option_key . '['. $args['id'] .']">';
        // var_dump($args['options']); exit;
        foreach ( $args['options'] as $key => $label) {
            $html .= '<option value="' . $key . '" ' . ($key === $value ? 'selected' : '') .'>' . $label . '</option>';
        }
        $html .= '</select>'; 
        $html .= $this->render_field_description( $args ); 
        echo $html;
    }

    public function field_callback_multiselect( $args ) {
        $values = $this->get_option( $args['id'] ) ?: array();
        $html = '<select name="' . $this->option_key . '['. $args['id'] .'][]" class="wooless-multiple-select" multiple="multiple" data-placeholder="' . $args['placeholder'] . '">';
        foreach ( $args['options'] as $key => $label) {
            $html .= '<option value="' . $key . '" ' . (in_array($key, $values) ? 'selected' : '') .'>' . $label . '</option>';
        }
        $html .= '</select>'; 
        $html .= $this->render_field_description( $args ); 
        echo $html;
    }

    public function render_field_description( $args, $inline = false )
    {
        if ( isset( $args['description'] ) ) {
            if ( $inline ) {
                return '<label for="' . $args['id'] .'"> '  . $args['description'] . '</label>'; 
            }

            return '<label style="margin-top: 5px; display: block;" for="' . $args['id'] .'"> '  . $args['description'] . '</label>'; 
        }

        return '';
    }

    public function register_settings_navtab( $active_tab )
    {
        echo sprintf(
            '<a href="/wp-admin/admin.php?page=wooless-settings&tab=%s" class="nav-tab %s">%s</a>',
            $this->tab_key,
            $active_tab == $this->tab_key ? 'nav-tab-active' : '',
            $this->page_label
        );
    }

    public function render_settings_tab( $active_tab )
    {
        if ( $active_tab === $this->tab_key ) {
            settings_fields( $this->option_key ); 
            do_settings_sections( $this->option_key );
        }
    }

    public function render_settings_footer_tab( $active_tab ) {
        if ( $active_tab !== $this->tab_key ) return;

        $this->footer_callback();
    }

    public function footer_callback() {}
}
