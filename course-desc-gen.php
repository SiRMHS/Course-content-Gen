<?php
/*
Plugin Name: Course Content Description Generator
Description: افزودن دکمه تولید توضیحات دوره با استفاده از AI
Version: 1.0
Author: محمد حسن صفره
*/

add_action('admin_enqueue_scripts', function($hook) {
    global $post;
    if ($hook == 'post.php' && isset($post) && $post->post_type == 'dornalms_course') {
        wp_enqueue_script('course-desc-gen', plugin_dir_url(__FILE__).'desc-gen.js', ['jquery'], null, true);
        wp_localize_script('course-desc-gen', 'descGenData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('desc_gen_nonce')
        ]);
    }
});

add_action('edit_form_after_title', function($post) {
    if ($post->post_type == 'dornalms_course') {
        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.00002L9.66 8.66002L4 11L9.66 13.34L12 19L14.34 13.34L20 11L14.34 8.66002L12 3.00002ZM20 18L19 15L18 18L15 19L18 20L19 23L20 20L23 19L20 18ZM9 4L8 1L7 4L4 5L7 6L8 9L9 6L12 5L9 4Z" fill="currentColor"/>
        </svg>';

        echo '<button type="button" id="generate-desc-btn">' . $icon_svg . '<span>جنریت توضیحات</span></button>';
    }
});


add_action('admin_enqueue_scripts', function($hook) {
    if ('post.php' != $hook && 'post-new.php' != $hook) {
        return;
    }

    global $post;
    if (!$post || 'dornalms_course' != $post->post_type) {
        return;
    }

    $custom_css = "
        /* Keyframes for the rotating gradient animation */
        @keyframes rotate-gradient {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        /* Main button styling */
        #generate-desc-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* Space between icon and text */
            padding: 14px 28px;
            margin-top: 15px;
            margin-bottom: 15px;
            
            border: none;
            border-radius: 100px; /* Pill shape */
            font-family: 'IY', 'Vazirmatn', sans-serif; /* فونت مناسب فارسی */
            font-size: 16px;
            font-weight: 700;
            
            /* Default state (grey) */
            background-color: #2a2a2a;
            color: #888;
            cursor: pointer;
            
            overflow: hidden; /* Important for the pseudo-element */
            transition: color 0.4s ease-in-out;
        }

        /* Styling for the SVG icon inside the button */
        #generate-desc-btn svg {
            width: 22px;
            height: 22px;
            fill: #888; /* Default icon color */
            transition: fill 0.4s ease-in-out;
            z-index: 2; /* Keep icon above the gradient */
        }
        
        #generate-desc-btn span {
            z-index: 2; /* Keep text above the gradient */
        }

        /* The rotating gradient pseudo-element */
        #generate-desc-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200%;
            height: 200%;
            z-index: 1;
            background-image: conic-gradient(#2999ff, #5851db, #e4405f, #fbb45c, #2999ff);
            opacity: 0;
            transform: translate(-50%, -50%) rotate(0deg);
            transition: opacity 0.4s ease-in-out;
            animation: rotate-gradient 4s linear infinite paused; /* Paused by default */
        }

        /* Hover state */
        #generate-desc-btn:hover {
            color: #fff; /* Brighten text on hover */
        }
        
        #generate-desc-btn:hover svg {
            fill: #fff; /* Brighten icon on hover */
        }

        #generate-desc-btn:hover::before {
            opacity: 1; /* Show the gradient */
            animation-play-state: running; /* Start the rotation animation */
        }
        
        /* A pseudo-element to create the inner dark background on top of the gradient */
        #generate-desc-btn::after {
            content: '';
            position: absolute;
            inset: 2px; /* This creates the border thickness */
            background-color: #1c1c1c; /* Inner color of the button */
            border-radius: 98px; /* Slightly smaller than the button's radius */
            z-index: 1;
        }
    ";

    wp_add_inline_style('wp-admin', $custom_css);
});

add_action('wp_ajax_generate_course_desc', function() {
    check_ajax_referer('desc_gen_nonce', 'nonce');
    $title = sanitize_text_field($_POST['title'] ?? '');
    $desc = sanitize_textarea_field($_POST['desc'] ?? '');
    $json = wp_unslash($_POST['json'] ?? '');
    $prompt = "عنوان دوره: $title\nتوضیحات فعلی: $desc\nسرفصل‌ها و ویدیوها: $json\nبر اساس این اطلاعات یک توضیحات جذاب و کامل برای معرفی این دوره بنویس.";
    if (!session_id()) {
        session_start();
    }
    $session_id = session_id();
    $response = wp_remote_post('https://n8n.nemove.ir/webhook/da616d96-f929-48f2-ba1b-1a328670311b', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'prompt' => $prompt,
            'session_id' => $session_id
        ]),
        'timeout' => 30
    ]);
    if (is_wp_error($response)) {
        wp_send_json_error(['msg' => 'خطا در ارتباط با API']);
    }
    $raw_response = wp_remote_retrieve_body($response);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[CourseDescGen] API response: " . $raw_response);
    }
    $body = json_decode($raw_response, true);
    $text = '';
    if (isset($body['desc'])) {
        $text = $body['desc'];
    } elseif (isset($body['output'])) {
        $text = $body['output'];
    } elseif (is_array($body) && isset($body[0]['output'])) {
        $text = $body[0]['output'];
    }
    if (!$text) {
        wp_send_json_error(['msg' => 'پاسخی از API دریافت نشد', 'raw' => $raw_response]);
    }
    wp_send_json_success(['desc' => $text, 'raw' => $raw_response]);
});
