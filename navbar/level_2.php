<?php
require_once '../config/menu_config.php';

function renderLevel2Navbar($approval_templates, $employee_name) {
    global $menu_config;
    $output = '<nav class="navbar">';
    
    // Logo (left)
    $output .= '<div class="navbar-logo"><img src="../media/allyted-logo2 (2).png" alt="Allyted Logo"></div>';
    
    // Hamburger toggle (for mobile menu)
    $output .= '<button class="navbar-toggle"><span></span><span></span><span></span></button>';
    
    // Menus (middle)
    $output .= '<div class="navbar-menus"><ul>';
    $static_menus = array_slice($menu_config['static'], 0, 2);
    foreach ($static_menus as $menu => $url) {
        $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
    }
    if (!empty($approval_templates)) {
        foreach ($approval_templates as $template) {
            if (isset($menu_config['dynamic'][$template]) && isset($menu_config['dynamic'][$template]['url'])) {
                $menu = $menu_config['dynamic'][$template];
                $output .= '<li><a href="../' . htmlspecialchars($menu['url']) . '">' . htmlspecialchars($menu['title']) . '</a></li>';
            }
        }
    }
    $remaining_menus = array_slice($menu_config['static'], 2);
    foreach ($remaining_menus as $menu => $url) {
        $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
    }
    $output .= '</ul></div>';
    
    // Right-side elements
    $output .= '<div class="navbar-right">';
    // Notifications icon
    $output .= '<a href="../templates/notifications.php" class="notification-icon" title="Notifications"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4a2 2 0 002-2v-1m-4 3v-3m0 0H8a2 2 0 01-2-2v-7a2 2 0 012-2h8a2 2 0 012 2v7a2 2 0 01-2 2h-4m-4 0h4m-4-7h8"></path></svg></a>';
    // Sun/moon theme toggle
    $output .= '<input type="checkbox" id="theme-toggle" class="theme-toggle-input">';
    $output .= '<label for="theme-toggle" class="theme-toggle">';
    $output .= '<div class="circle">';
    $output .= '<svg class="sun icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM18.894 6.166a.75.75 0 0 0-1.06-1.06l-1.591 1.59a.75.75 0 1 0 1.06 1.061l1.591-1.59ZM21.75 12a.75.75 0 0 1-.75.75h-2.25a.75.75 0 0 1 0-1.5H21a.75.75 0 0 1 .75.75ZM17.834 18.894a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 1 0-1.061 1.06l1.59 1.591ZM12 18a.75.75 0 0 1 .75.75V21a.75.75 0 0 1-1.5 0v-2.25A.75.75 0 0 1 12 18ZM7.758 17.303a.75.75 0 0 0-1.061-1.06l-1.591 1.59a.75.75 0 0 0 1.06 1.061l1.591-1.59ZM6 12a.75.75 0 0 1-.75.75H3a.75.75 0 0 1 0-1.5h2.25A.75.75 0 0 1 6 12ZM6.697 7.757a.75.75 0 0 0 1.06-1.06l-1.59-1.591a.75.75 0 0 0-1.061 1.06l1.59 1.591Z" /></svg>';
    $output .= '<svg class="moon icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" /></svg>';
    $output .= '</div></label>';
    // User dropdown
    $output .= '<div class="user-profile dropdown-parent">';
    $output .= '<img src="../media/default_photo.png" alt="Profile Photo" class="profile-photo"><span>' . htmlspecialchars($employee_name) . '</span><svg class="dropdown-icon icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    $output .= '<ul class="dropdown">';
    if (isset($menu_config['user_dropdown'])) {
        foreach ($menu_config['user_dropdown'] as $menu => $url) {
            if ($menu === 'Logout') {
                $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '<svg class="icon ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg></a></li>';
            } else {
                $output .= '<li><a href="../' . htmlspecialchars($url) . '">' . htmlspecialchars($menu) . '</a></li>';
            }
        }
    }
    $output .= '</ul></div>';
    $output .= '</div></nav>';
    return $output;
}

echo renderLevel2Navbar($_SESSION['approval_templates'] ?? [], $_SESSION['employee_name'] ?? 'User');
?>