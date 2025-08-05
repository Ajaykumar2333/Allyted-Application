<?php
require_once '../config/menu_config.php';

function renderNavbar($approval_templates = [], $employee_name = 'User', $include_level3_extras = false) {
    global $menu_config;

    $employee_name = $employee_name ?: ($_SESSION['employee_name'] ?? 'User');
    $approval_templates = $approval_templates ?: ($_SESSION['approval_templates'] ?? []);

    $output = '<nav class="navbar">';
    $output .= '<div class="navbar-logo"><img src="../media/allyted-logo2 (2).png" alt="Allyted Logo"></div>';
    $output .= '<button class="navbar-toggle"><span></span><span></span><span></span></button>';

    // Start Menus
    $output .= '<div class="navbar-menus"><ul>';

    // First 2 static menus
    $static_menus = array_slice($menu_config['static'], 0, 2);
    foreach ($static_menus as $menu => $url) {
        $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
    }

    // Dynamic menus
    foreach ($approval_templates as $template) {
        if (isset($menu_config['dynamic'][$template])) {
            $menu = $menu_config['dynamic'][$template];
            $output .= '<li><a href="../' . htmlspecialchars($menu['url']) . '">' . htmlspecialchars($menu['title']) . '</a></li>';
        }
    }

    // Remaining static menus
    $remaining_menus = array_slice($menu_config['static'], 2);
    foreach ($remaining_menus as $menu => $url) {
        $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
    }

    // Optional Level 3 extras
    if ($include_level3_extras && isset($menu_config['level3_extras'])) {
        foreach ($menu_config['level3_extras'] as $menu => $url) {
            $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
        }
    }

    $output .= '</ul></div>'; // end menus

    // Right section
    $output .= '<div class="navbar-right">';
    $output .= '<a href="../templates/notifications.php" class="notification-icon" title="Notifications">🔔</a>';

    // Dark mode toggle (replace with SVGs if needed)
    $output .= '<input type="checkbox" id="theme-toggle" class="theme-toggle-input">';
    $output .= '<label for="theme-toggle" class="theme-toggle"><div class="circle">🌞🌙</div></label>';

    // User dropdown
    $output .= '<div class="user-profile dropdown-parent">';
    $output .= '<img src="../media/default_photo.png" alt="Profile Photo" class="profile-photo">';
    $output .= '<span>' . htmlspecialchars($employee_name) . '</span>';
    $output .= '<ul class="dropdown">';
    foreach ($menu_config['user_dropdown'] as $menu => $url) {
        $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
    }
    $output .= '</ul></div>'; // user-profile
    $output .= '</div>'; // navbar-right
    $output .= '</nav>';

    return $output;
}
?>
