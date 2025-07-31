<?php
// Handle form submissions
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = sanitize_text_field($_POST['username']);
    $password = $_POST['password'];
    
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        $login_error = 'Invalid username or password.';
    } else {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        wp_redirect(home_url());
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'signup') {
    $username = sanitize_text_field($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $consent = isset($_POST['consent']) ? true : false;
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $signup_error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $signup_error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $signup_error = 'Password must be at least 6 characters.';
    } elseif (!is_email($email)) {
        $signup_error = 'Please enter a valid email address.';
    } elseif (!$consent) {
        $signup_error = 'You must agree to receive promotional emails.';
    } else {
        // Check if user already exists
        if (username_exists($username)) {
            $signup_error = 'Username already exists.';
        } elseif (email_exists($email)) {
            $signup_error = 'Email already exists.';
        } else {
            // Create user
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                $signup_error = $user_id->get_error_message();
            } else {
                // Log in the new user
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                wp_redirect(home_url());
                exit;
            }
        }
    }
}

// Helper functions for content extraction
function extract_video_url_from_content($content) {
    if (preg_match('/<a\s+href=["\']([^"\']+)["\']/', $content, $matches)) return esc_url($matches[1]);
    return '';
}
function extract_image_url_from_content($content) {
    if (preg_match('/<img\s+src=["\']([^"\']+)["\']/', $content, $matches)) return esc_url($matches[1]);
    return '';
}
function extract_views_from_content($content) {
    if (preg_match('/Views:\s*([0-9,]+)/i', $content, $matches)) return (int)str_replace(',', '', $matches[1]);
    return 0;
}
function extract_title_from_content($content) {
    if (preg_match('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches)) return wp_strip_all_tags($matches[1]);
    return '';
}
function extract_upload_date_from_excerpt($excerpt) {
    $date = trim($excerpt);
    if (!$date) return false;
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $date, $m)) return strtotime($m[1]);
    if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $date, $m)) {
        return strtotime("{$m[3]}-{$m[2]}-{$m[1]}");
    }
    $ts = strtotime($date);
    return $ts && $date !== '' ? $ts : false;
}

// Menu and footer links (same as search.php)
$menu = [
    ["Home", "/home"],["Most Viewed", "/most-popular"],["Series (Day x)", "/series-day-x"],
    ["Recent Upload", "/recent-uploads"],["Category", "/category"],["Random", "/random"],["Search", "/search"]
];
$footer_links = [
    ["Cookies Policy", "/cookies-policy"],["Privacy Policy", "/privacy-policy"],["Terms and Services", "/terms-services"]
];

// Category slugs in the order you want
$category_slugs = ['stepmom', 'stepsis', 'milf', 'teacher'];
$categories = [];
foreach ($category_slugs as $slug) {
    $cat = get_category_by_slug($slug);
    if ($cat) $categories[] = $cat;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<title><?php bloginfo('name'); ?> - Home</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body { background:#161424; color:#fff; font-family:'Inter',sans-serif; margin:0; padding-top:0; overflow-x: hidden; }
a { color:inherit; text-decoration:none; }

/* Sticky Header Styles */
.header-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(22, 20, 36, 0.98);
    backdrop-filter: blur(15px);
    border-bottom: 1px solid rgba(178, 164, 248, 0.2);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(0);
}

.header-container.sticky {
    background: rgba(22, 20, 36, 0.99);
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.4);
    border-bottom: 1px solid rgba(178, 164, 248, 0.3);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    max-width: 1400px;
    margin: 0 auto;
    height: 60px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 25px;
}

.header-logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: #b2a4f8;
    text-decoration: none;
    transition: color 0.3s ease;
}

.header-logo:hover {
    color: #fff;
}

.header-home {
    color: #e8e6f0;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.3s ease;
    padding: 8px 12px;
    border-radius: 6px;
}

.header-home:hover {
    color: #b2a4f8;
    background: rgba(178, 164, 248, 0.1);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-right: 20px;
}

/* Hamburger Menu */
#hamburger { 
    width: 32px; 
    height: 32px; 
    background: none; 
    border: none; 
    cursor: pointer; 
    display: flex; 
    flex-direction: column; 
    justify-content: center; 
    align-items: center; 
    transition: all 0.3s ease; 
    border-radius: 6px;
    padding: 4px;
}

#hamburger:hover { 
    background: rgba(178, 164, 248, 0.1); 
}

#hamburger span { 
    display: block; 
    width: 20px; 
    height: 2px; 
    background: #fff; 
    border-radius: 1px; 
    margin: 2px 0; 
    transition: all 0.3s ease;
}

#hamburger:hover span {
    background: #b2a4f8;
}

/* Search Bar */
.search-container {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    max-width: 700px;
    margin: 0 40px;
}

.search-bar-wrap { 
    width: 100%;
    max-width: 600px;
    position: relative;
}

#search-bar { 
    width: 100%; 
    padding: 12px 20px; 
    font-size: 1rem; 
    border-radius: 25px; 
    border: 1px solid rgba(178, 164, 248, 0.3); 
    background: rgba(34, 32, 69, 0.8); 
    color: #fff; 
    outline: none; 
    transition: all 0.3s ease;
}

#search-bar:focus { 
    border-color: #b2a4f8; 
    background: rgba(34, 32, 69, 0.95);
    box-shadow: 0 0 0 3px rgba(178, 164, 248, 0.1);
}

#search-bar::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

#search-bar {
    cursor: pointer;
}

/* Search Overlay Styles */
.search-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(10px);
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.search-overlay.active {
    opacity: 1;
    visibility: visible;
}

.search-overlay-content {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    transform: translateY(-20px);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.search-overlay.active .search-overlay-content {
    transform: translateY(0);
}

.search-overlay-header {
    padding: 80px 40px 40px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.search-overlay-input-container {
    position: relative;
    width: 100%;
    max-width: 800px;
    display: flex;
    align-items: center;
}

#search-overlay-input {
    width: 100%;
    padding: 20px 70px 20px 30px;
    font-size: 2.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(178, 164, 248, 0.3);
    border-radius: 50px;
    color: #fff;
    outline: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

#search-overlay-input:focus {
    border-color: #b2a4f8;
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 0 0 4px rgba(178, 164, 248, 0.2);
}

#search-overlay-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
    font-weight: 300;
}

.search-overlay-close {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 3rem;
    cursor: pointer;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.search-overlay-close:hover {
    color: #b2a4f8;
    background: rgba(178, 164, 248, 0.1);
    transform: translateY(-50%) scale(1.1);
}

.search-overlay-results {
    flex: 1;
    padding: 0 40px 40px;
    overflow-y: auto;
}

.search-suggestions {
    max-width: 800px;
    margin: 0 auto;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.1s;
}

.search-overlay.active .search-suggestions {
    opacity: 1;
    transform: translateY(0);
}

.suggestion-category {
    margin-bottom: 40px;
}

.suggestion-category h4 {
    color: #b2a4f8;
    font-size: 1.2rem;
    margin-bottom: 15px;
    font-weight: 600;
}

.suggestion-items {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.suggestion-item {
    background: rgba(178, 164, 248, 0.1);
    color: rgba(255, 255, 255, 0.9);
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(178, 164, 248, 0.2);
    font-size: 0.9rem;
}

.suggestion-item:hover {
    background: rgba(178, 164, 248, 0.2);
    border-color: #b2a4f8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(178, 164, 248, 0.3);
}

.search-results-content {
    max-width: 800px;
    margin: 0 auto;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.2s;
}

.search-overlay.active .search-results-content {
    opacity: 1;
    transform: translateY(0);
}

/* Responsive Design */
@media (max-width: 768px) {
    .search-overlay-header {
        padding: 60px 20px 30px;
    }
    
    #search-overlay-input {
        font-size: 1.8rem;
        padding: 15px 60px 15px 25px;
    }
    
    .search-overlay-close {
        font-size: 2.5rem;
        width: 40px;
        height: 40px;
        right: 15px;
    }
    
    .search-overlay-results {
        padding: 0 20px 30px;
    }
}

@media (max-width: 480px) {
    #search-overlay-input {
        font-size: 1.5rem;
        padding: 12px 50px 12px 20px;
    }
    
    .search-overlay-close {
        font-size: 2rem;
        width: 35px;
        height: 35px;
        right: 12px;
    }
}

/* Auth Buttons */
.auth-btn {
    background: linear-gradient(135deg, #3a2d70, #2a2754);
    color: #fff;
    padding: 8px 16px;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(178, 164, 248, 0.3);
}

.auth-btn:hover {
    background: linear-gradient(135deg, #4a3d80, #3a2d70);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(80, 60, 180, 0.3);
    border-color: #b2a4f8;
}

/* Mobile Menu */
#mobilemenu, #overlay { display: none; }
#mobilemenu { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 280px; 
    max-width: 90vw; 
    height: 100vh; 
    background: rgba(22, 20, 36, 0.98); 
    z-index: 2000; 
    box-shadow: 4px 0 32px rgba(0,0,0,0.3); 
    padding: 80px 0 0 0; 
    flex-direction: column; 
    gap: 0; 
    border-top-right-radius: 20px;
    backdrop-filter: blur(10px);
}

#mobilemenu.open { 
    display: flex; 
    animation: menuOpen 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes menuOpen { 
    from { transform: translateX(-100%); opacity: 0; } 
    to { transform: translateX(0); opacity: 1; } 
}

#mobilemenu a { 
    color: #fff; 
    font-size: 1.1rem; 
    font-weight: 500; 
    padding: 16px 24px; 
    transition: all 0.3s ease;
}

#mobilemenu a:hover { 
    color: #b2a4f8; 
    background: rgba(178, 164, 248, 0.1); 
}

#overlay { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100vw; 
    height: 100vh; 
    background: rgba(22, 20, 36, 0.8); 
    backdrop-filter: blur(5px); 
    z-index: 1900; 
}

/* Responsive Header */
@media (max-width: 768px) {
    .header-content {
        padding: 10px 15px;
        height: 50px;
    }
    
    .header-home {
        display: none;
    }
    
    .search-container {
        max-width: 200px;
        margin: 0 10px;
    }
    
    #search-bar {
        font-size: 0.9rem;
        padding: 8px 12px;
    }
    
    .auth-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .header-content {
        padding: 8px 12px;
        height: 45px;
    }
    
    .header-logo {
        font-size: 1.2rem;
    }
    
    .search-container {
        max-width: 150px;
        margin: 0 8px;
    }
    
    #search-bar {
        font-size: 0.85rem;
        padding: 6px 10px;
    }
    
    .auth-btn {
        padding: 5px 10px;
        font-size: 0.75rem;
    }
}

.carousel-container { 
    margin-top: 60px; 
    padding: 0; 
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto; 
    overflow-y: hidden; 
    position: relative; 
    width: 100vw; 
    overscroll-behavior-x: none; /* Prevent horizontal over-scrolling */
    overscroll-behavior-y: auto; /* Allow vertical scroll chaining */
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch; /* Smooth momentum on iOS/Touch */
    touch-action: pan-x pan-y; /* Allow both horizontal carousel and vertical page scrolling */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
    scroll-snap-type: x mandatory; /* Enable horizontal scroll snapping */
    cursor: grab; /* Show grab cursor for drag interaction */
    user-select: none; /* Prevent text selection during drag */
}

.carousel-container:active {
    cursor: grabbing; /* Show grabbing cursor when dragging */
}

.carousel-container::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

.carousel { 
    display: flex; 
    flex-wrap: nowrap;
    /* Remove fixed width - let content determine width */
}
.slide {
  min-width: 100vw;
  max-width: 100vw;
  width: 100vw;
  flex: 0 0 auto; /* Prevent items from shrinking, allow natural sizing */
  scroll-snap-align: start; /* Snap to start of each slide */
  scroll-snap-stop: always; /* Force stop at each slide */
  height: 100vh;
  min-height: unset;
  max-height: unset;
  background: #23204a;
  border-radius: 0;
  box-shadow: 0 4px 32px 0 rgba(40,30,80,0.18);
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: stretch;
  justify-content: flex-start;
}
/* Slide image: different for first slide and others */
.slide-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 1;
}

/* Category slides blend effect */
.slide:not(:first-child) .slide-image {
  position: relative;
}

.slide:not(:first-child)::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 120px;
  background: linear-gradient(to top, rgba(22,20,36,0.8), transparent);
  pointer-events: none;
  z-index: 2;
}
.slide:first-child .slide-image {
  opacity: 0.7;
  filter: brightness(0.7);
}
.slide:not(:first-child) .slide-image {
  opacity: 0.8;
  filter: brightness(0.8);
}
/* Slide overlay: different for first slide and others */
.slide-overlay {
  position: absolute;
  z-index: 2;
}
.slide:first-child .slide-overlay {
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(22,20,36,0.5);
}
.slide:not(:first-child) .slide-overlay {
  bottom: 0;
  left: 0;
  width: 100%;
  height: 150px;
  background-color: rgba(22,20,36,0.7);
}
/* Slide content: center for first, bottom for others */
.slide-content {
  position: absolute;
  z-index: 3;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  max-width: 800px;
  padding: 32px;
  background: rgba(34,32,69,0.6);
  border-radius: 16px;
  text-align: center;
  width: auto;
}
.slide:not(:first-child) .slide-content {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  transform: none;
  max-width: unset;
  padding: 24px;
  background: none;
  border-radius: 0;
  text-align: left;
}
/* Slide title */
.slide-title {
  font-size: 3rem;
  font-weight: 800;
  color: #f7cfff;
  text-shadow: 0 2px 8px #18162a;
  margin-bottom: 16px;
}
.slide:not(:firsot-child) .slide-title {
  font-size: 2.8rem;
  font-weight: 700;
  margin-bottom: 12px;
}
/* Slide desc */
.slide-desc {
  font-size: 1.5rem;
  color: #e0e0e0;
  line-height: 1.4;
}
.slide:not(:first-child) .slide-desc {
  font-size: 1.3rem;
}

.slide-link {
  display: block;
  width: 100%;
  height: 100%;
  text-decoration: none;
  color: inherit;
  position: relative;
  z-index: 1;
}
/* Responsive styles */
@media (max-width: 900px) {
  .slide-title { font-size: 2rem; }
  .slide-desc { font-size: 1.2rem; }
  .slide-content { padding: 20px; max-width: 90vw; }
  .slide:not(:first-child) .slide-title { font-size: 2rem; }
  .slide:not(:first-child) .slide-desc { font-size: 1.1rem; }
  .slide:not(:first-child) .slide-content { padding: 16px; }
}
@media (max-width: 700px) {
  .slide-title { font-size: 1.5rem; }
  .slide-desc { font-size: 1rem; }
  .slide-content { padding: 16px; }
  .slide:not(:first-child) .slide-title { font-size: 1.5rem; }
  .slide:not(:first-child) .slide-desc { font-size: 0.9rem; }
  .slide:not(:first-child) .slide-content { padding: 12px; }
  .slide:not(:first-child) .slide-overlay { height: 120px; }
}


/* Content Sections Styles */
.content-sections {
    max-width: 1500px;
    margin: 0 auto;
    padding: 40px 20px;
}

.content-section {
    margin-bottom: 60px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 0 10px;
}

.section-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f7cfff;
    margin: 0;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.view-more-btn {
    background: linear-gradient(135deg, #3a2d70, #2a2754);
    color: #fff;
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: 2px solid #4a3d80;
    box-shadow: 0 4px 15px rgba(80,60,180,0.2);
}

.view-more-btn:hover {
    background: linear-gradient(135deg, #4a3d80, #3a2d70);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(80,60,180,0.3);
    color: #fff;
}

.horizontal-scroll {
    overflow: hidden;
    position: relative;
}

.scroll-container {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 10px 0;
    cursor: grab;
    scrollbar-width: none;
    -ms-overflow-style: none;
    user-select: none;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
    width: 100%;
    touch-action: pan-x pan-y; /* Allow both horizontal and vertical touch actions */
    overscroll-behavior: auto; /* Allow scroll chaining to parent for vertical scroll */
}

.scroll-container::-webkit-scrollbar {
    display: none;
}

.scroll-item {
    background: #222045;
    border: 2px solid #2a2754;
    border-radius: 18px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 18px 0 rgba(30,27,54,0.13);
    position: relative;
    width: calc(20% - 16px);
    min-width: 240px;
    max-width: 280px;
    margin: 0;
    transition: transform .13s, border-color .13s;
    flex-shrink: 0;
    pointer-events: auto; /* Ensure child elements can be interacted with */
}

.scroll-item:hover {
    transform: translateY(-6px) scale(1.018);
    border-color: #3a2d70;
    box-shadow: 0 8px 36px 0 rgba(80,60,180,0.11);
}

.scroll-item a {
    display: block;
    text-decoration: none;
    color: inherit;
}

.scroll-item img {
    display: block;
    width: 100%;
    height: auto;
    object-fit: contain;
    background: #141227;
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
}

.item-title {
    padding: 16px 12px;
    font-size: 1.05rem;
    font-weight: 800;
    color: #fff;
    text-align: center;
    margin: 0;
    line-height: 1.3;
    width: 100%;
    text-decoration: none;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-views, .item-count {
    padding: 0 18px 15px;
    font-size: 0.85rem;
    color: #b2a4f8;
    font-weight: 500;
}

.category-item {
    position: relative;
}

.category-item img {
    display: block;
    width: 100%;
    height: auto;
    object-fit: contain;
    background: #141227;
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
}

.category-item::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: linear-gradient(to top, rgba(34,32,69,0.95), transparent);
    pointer-events: none;
    border-bottom-left-radius: 16px;
    border-bottom-right-radius: 16px;
    z-index: 1;
}

/* Responsive Design */
@media (max-width: 1400px) {
    .scroll-item {
        width: calc(20% - 16px);
        min-width: 220px;
        max-width: 260px;
    }
}

@media (max-width: 1200px) {
    .scroll-item {
        width: calc(25% - 15px);
        min-width: 200px;
        max-width: 240px;
    }
}

@media (max-width: 900px) {
    .scroll-item {
        width: calc(33.333% - 13px);
        min-width: 180px;
        max-width: 220px;
    }
}

@media (max-width: 700px) {
    .content-sections {
        padding: 20px 10px;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
    
    .view-more-btn {
        padding: 8px 16px;
        font-size: 0.8rem;
    }
    
    .scroll-item {
        width: calc(50% - 10px);
        min-width: 160px;
        max-width: 200px;
    }
    
    .item-title {
        font-size: 0.9rem;
        padding: 12px 15px 8px;
    }
    
    .item-views, .item-count {
        font-size: 0.75rem;
        padding: 0 15px 12px;
    }
}

/* Footer Styles - Redesigned for Better UX */
#footermenuwrap { 
    background: linear-gradient(135deg, #181632, #1a1a2e); 
    padding: 50px 0 30px 0; 
    margin-top: 80px; 
    border-top: 1px solid rgba(178, 164, 248, 0.1);
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
}

.footer-section {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.footer-section h3 {
    color: #b2a4f8;
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.footer-section a {
    color: #e8e6f0;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    padding: 8px 0;
    transition: all 0.3s ease;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-section a:hover {
    color: #b2a4f8;
    background: rgba(178, 164, 248, 0.1);
    padding-left: 8px;
    transform: translateX(5px);
}

.footer-section a::before {
    content: '→';
    color: #b2a4f8;
    font-weight: bold;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.footer-section a:hover::before {
    opacity: 1;
}

.footer-bottom {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid rgba(178, 164, 248, 0.1);
    text-align: center;
}

#footerlegal { 
    color: #a5a2c5; 
    font-size: 0.9rem; 
    margin-bottom: 15px;
}

#footerlegal a { 
    color: #b2a4f8; 
    margin: 0 10px; 
    text-decoration: none;
    transition: color 0.3s ease;
}

#footerlegal a:hover {
    color: #fff;
}

#footerdisclaimer { 
    color: #8a8a9a; 
    font-size: 0.85rem; 
    line-height: 1.5;
    max-width: 600px;
    margin: 0 auto;
}

/* Responsive Footer */
@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        gap: 30px;
        text-align: center;
    }
    
    .footer-section a {
        justify-content: center;
    }
    
    .footer-section a:hover {
        transform: translateY(-2px);
    }
    
    .footer-section a::before {
        display: none;
    }
}

/* Auth Button */
.auth-btn {
    background: linear-gradient(135deg, #3a2d70, #2a2754);
    color: #fff;
    padding: 10px 20px;
    border: none;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #4a3d80;
    box-shadow: 0 4px 15px rgba(80,60,180,0.2);
    margin-left: 15px;
}

.auth-btn:hover {
    background: linear-gradient(135deg, #4a3d80, #3a2d70);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(80,60,180,0.3);
}

/* Auth Modal */
.auth-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(22, 20, 36, 0.8);
    backdrop-filter: blur(10px);
    z-index: 3000;
    align-items: center;
    justify-content: center;
}

.auth-modal.active {
    display: flex !important;
    animation: modalFadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.auth-modal-content {
    animation: contentSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes contentSlideIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.auth-modal-content {
    background: #222045;
    border: 2px solid #2a2754;
    border-radius: 20px;
    padding: 40px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}

.auth-close-btn {
    position: absolute;
    top: 15px;
    right: 20px;
    background: none;
    border: none;
    color: #b2a4f8;
    font-size: 24px;
    cursor: pointer;
    transition: color 0.3s ease;
}

.auth-close-btn:hover {
    color: #fff;
}

/* Auth Title */
.auth-title {
    text-align: center;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 30px;
    margin-top: 0;
}

/* Auth Switch */
.auth-switch {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #2a2754;
}

.auth-switch p {
    color: #b2a4f8;
    font-size: 0.9rem;
    margin: 0;
}

.auth-switch a {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.auth-switch a:hover {
    color: #b2a4f8;
}

.auth-error {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
    color: #dc3545;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    text-align: center;
}

/* Auth Forms */
.auth-form {
    display: none;
}

.auth-form.active {
    display: block;
}

.form-group {
    margin-bottom: 20px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"] {
    width: 100%;
    padding: 15px;
    border: 2px solid #2a2754;
    border-radius: 10px;
    background: #1a1a2e;
    color: #fff;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
}

.form-group input:focus {
    outline: none;
    border-color: #2a2754;
}

/* Premium input animation - only for text inputs */
.form-group:has(input[type="text"]),
.form-group:has(input[type="email"]),
.form-group:has(input[type="password"]) {
    position: relative;
}

.form-group:has(input[type="text"])::after,
.form-group:has(input[type="email"])::after,
.form-group:has(input[type="password"])::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #b2a4f8, #8b7cf6);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-50%);
    border-radius: 1px;
}

.form-group:has(input[type="text"]):focus-within::after,
.form-group:has(input[type="email"]):focus-within::after,
.form-group:has(input[type="password"]):focus-within::after {
    width: calc(100% - 20px);
}

.checkbox-group {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 8px 0;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #b2a4f8;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin: 0;
    margin-top: 3px;
}

.checkbox-group input[type="checkbox"]:hover {
    transform: scale(1.1);
}

.checkbox-group span {
    font-size: 0.9rem;
    color: #b2a4f8;
    line-height: 1.4;
    cursor: pointer;
    flex: 1;
    padding-top: 1px;
}

.auth-submit-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #3a2d70, #2a2754);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
    position: relative;
    overflow: hidden;
}

.auth-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s ease;
}

.auth-submit-btn:hover::before {
    left: 100%;
}

.auth-submit-btn:hover {
    background: linear-gradient(135deg, #4a3d80, #3a2d70);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(80,60,180,0.3);
}



.auth-modal-content {
    background: linear-gradient(135deg, #222045, #1e1b36);
    border: 2px solid #2a2754;
    border-radius: 20px;
    padding: 40px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    backdrop-filter: blur(10px);
}

.auth-modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(178,164,248,0.1), transparent);
    border-radius: 20px;
    pointer-events: none;
}

.auth-title {
    text-align: center;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 30px;
    margin-top: 0;
    text-shadow: 0 2px 10px rgba(255,255,255,0.1);
    position: relative;
}



/* Responsive */
@media (max-width: 480px) {
    .auth-modal-content {
        padding: 30px 20px;
        width: 95%;
    }
    
    .auth-tab {
        font-size: 1rem;
        padding: 12px;
    }
    
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"] {
        padding: 12px;
        font-size: 0.9rem;
    }
}
</style>
</head>
<body <?php body_class(); ?>>
<!-- Sticky Header -->
<header class="header-container" id="header">
    <div class="header-content">
        <div class="header-left">
            <button id="hamburger" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <a href="/home" class="header-logo">anifinder</a>
            <a href="/home" class="header-home">Home</a>
        </div>
        
        <div class="search-container">
            <div class="search-bar-wrap">
                <input id="search-bar" type="text" placeholder="Search..." autocomplete="off" readonly>
            </div>
        </div>
        
        <div class="header-right">
            <button id="login-signup-btn" class="auth-btn">Login</button>
        </div>
    </div>
</header>

<!-- Search Overlay -->
<div id="search-overlay" class="search-overlay">
    <div class="search-overlay-content">
        <div class="search-overlay-header">
            <div class="search-overlay-input-container">
                <input id="search-overlay-input" type="text" placeholder="Search..." autocomplete="off">
                <button id="search-overlay-close" class="search-overlay-close">&times;</button>
            </div>
        </div>
        <div class="search-overlay-results">
            <div class="search-suggestions">
                <div class="suggestion-category">
                    <h4>Popular Searches</h4>
                    <div class="suggestion-items">
                        <span class="suggestion-item">stepmom</span>
                        <span class="suggestion-item">milf</span>
                        <span class="suggestion-item">teacher</span>
                        <span class="suggestion-item">stepsis</span>
                    </div>
                </div>
                <div class="suggestion-category">
                    <h4>Recent Searches</h4>
                    <div class="suggestion-items" id="recent-searches">
                        <!-- Recent searches will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="search-results-content" id="search-results-content">
                <!-- Search results will be populated here -->
            </div>
        </div>
    </div>
</div>

<div id="overlay"></div>
<div id="mobilemenu">
    <?php foreach($menu as $m) {
        $url = $m[1] === "/series-day-x" ? "series day(x).php" : $m[1];
        echo '<a href="'.$url.'">'.$m[0].'</a>';
    } ?>
</div>

<!-- Carousel -->
<div class="carousel-container">
    <div class="carousel" id="carousel">
        <!-- Slide 1: Intro -->
        <div class="slide">
            <img class="slide-image" src="YOUR_INTRO_IMAGE_URL_HERE" alt="Intro">
            <div class="slide-overlay"></div>
            <div class="slide-content">
                <div class="slide-title">Stream HD Hentai & Anime Free</div>
                <div class="slide-desc">Dive into our curated collection of 720p/1080p hentai and anime videos, available for free on mobile, tablet, and desktop.</div>
            </div>
        </div>
        <!-- Slides 2-5: Categories -->
        <?php foreach ($categories as $cat): ?>
            <div class="slide">
                <a href="<?php echo esc_url(get_category_link($cat)); ?>" class="slide-link">
                    <?php
                    $image_url = get_stylesheet_directory_uri() . '/images/categories/' . $cat->slug . '.jpg';
                    $image_path = get_stylesheet_directory() . '/images/categories/' . $cat->slug . '.jpg';
                    if (!file_exists($image_path)) {
                        $image_url = get_stylesheet_directory_uri().'/assets/default-thumb.jpg';
                    }
                    ?>
                    <img class="slide-image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($cat->name); ?>">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <div class="slide-title"><?php echo esc_html($cat->name); ?></div>
                        <div class="slide-desc"><?php echo esc_html($cat->description); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script>
// Hamburger logic (from searchh.php)
const hamburger = document.getElementById('hamburger'),
      overlay = document.getElementById('overlay'),
      mobilemenu = document.getElementById('mobilemenu');
let menuOpen = false;
hamburger.onclick = function(e) { 
    e.stopPropagation(); 
    mobilemenu.classList.add("open"); 
    overlay.style.display="block"; 
    menuOpen = true; 
    this.style.backgroundColor = 'rgba(255,255,255,0.2)';
    setTimeout(() => { this.style.backgroundColor = ''; }, 200);
};
overlay.onclick = closeMenu;
function closeMenu() { 
    mobilemenu.classList.remove("open"); 
    overlay.style.display="none"; 
    menuOpen = false; 
}
mobilemenu.onmouseleave = closeMenu;
mobilemenu.onclick = function(e) { if(e.target.tagName==="A"){ closeMenu(); } };
document.body.addEventListener('mousedown',function(e){
    if(menuOpen && !mobilemenu.contains(e.target) && !hamburger.contains(e.target)) closeMenu();
});
// Scroll-based Carousel logic with proper horizontal overflow
const carousel = document.getElementById('carousel');
const carouselContainer = document.querySelector('.carousel-container');
const slides = carousel.children;
let currentIndex = 0;
const slideCount = 5; // Exactly 5 slides
let autoScrollInterval;
let autoScrollStopped = false;
let isManualScrolling = false;

// Get slide width
function getSlideWidth() {
    return window.innerWidth;
}

// Go to specific slide by index (0-4) using scrollLeft
function goToSlide(index, useTransition = true) {
    // Enforce boundaries
    if (index < 0) index = 0;
    if (index >= slideCount) index = slideCount - 1;
    
    currentIndex = index;
    const scrollPosition = index * getSlideWidth();
    
    if (useTransition) {
        carouselContainer.scrollTo({
            left: scrollPosition,
            behavior: 'smooth'
        });
    } else {
        carouselContainer.scrollLeft = scrollPosition;
    }
}

// Auto-scroll function - goes through all 5 slides once using scrollBy
function startAutoScroll() {
    if (autoScrollStopped) return;
    
    let autoScrollCount = 0;
    
    autoScrollInterval = setInterval(() => {
        // Only stop auto-scroll if manually stopped
        if (autoScrollStopped || isManualScrolling) {
            clearInterval(autoScrollInterval);
            autoScrollInterval = null;
            return;
        }
        
        // Auto-scroll to next slide
        const slideWidth = getSlideWidth();
        carouselContainer.scrollBy({ left: slideWidth, behavior: 'smooth' });
        
        autoScrollCount++;
        
        if (autoScrollCount >= 4) {
            // Stop after 4 moves (slide 1→2→3→4→5)
            clearInterval(autoScrollInterval);
            autoScrollInterval = null;
        }
    }, 2000); // Every 2 seconds (2000ms)
}

// Stop auto-scroll permanently
function stopAutoScroll() {
    if (autoScrollInterval) {
        clearInterval(autoScrollInterval);
        autoScrollInterval = null;
    }
    autoScrollStopped = true;
}

// Update current index based on scroll position
function updateCurrentIndex() {
    const scrollLeft = carouselContainer.scrollLeft;
    const slideWidth = getSlideWidth();
    const newIndex = Math.round(scrollLeft / slideWidth);
    
    // Enforce boundaries
    if (newIndex >= 0 && newIndex < slideCount) {
        currentIndex = newIndex;
    }
}

// Simplified scroll detection for native scrolling
let scrollTimeout;
let lastScrollLeft = 0;

carouselContainer.addEventListener('scroll', () => {
    const currentScrollLeft = carouselContainer.scrollLeft;
    
    // Detect any manual scrolling movement
    if (Math.abs(currentScrollLeft - lastScrollLeft) > 5) {
        if (!isManualScrolling) {
            isManualScrolling = true;
            stopAutoScroll();
        }
        
        lastScrollLeft = currentScrollLeft;
        
        // Optional: Add scroll snapping after user stops scrolling
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            isManualScrolling = false;
            // Snap to nearest slide
            updateCurrentIndex();
            goToSlide(currentIndex, true);
        }, 300);
    }
});

// Drag-to-scroll functionality (mouse)
let isDown = false;
let startX;
let scrollLeft;

carouselContainer.addEventListener('mousedown', (e) => {
    isDown = true;
    carouselContainer.style.cursor = 'grabbing';
    startX = e.pageX - carouselContainer.offsetLeft;
    scrollLeft = carouselContainer.scrollLeft;
    stopAutoScroll();
});

carouselContainer.addEventListener('mouseleave', () => {
    isDown = false;
    carouselContainer.style.cursor = 'grab';
});

carouselContainer.addEventListener('mouseup', () => {
    isDown = false;
    carouselContainer.style.cursor = 'grab';
});

carouselContainer.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - carouselContainer.offsetLeft;
    const walk = (x - startX) * 2; // scroll-fast multiplier
    carouselContainer.scrollLeft = scrollLeft - walk;
});

// Touch event handling for mobile
let touchStartX = 0;
let touchStartY = 0;

carouselContainer.addEventListener('touchstart', (e) => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    stopAutoScroll();
}, { passive: true });

carouselContainer.addEventListener('touchmove', (e) => {
    // Let the browser handle touch scrolling naturally
    const touchCurrentX = e.touches[0].clientX;
    const touchCurrentY = e.touches[0].clientY;
    const deltaX = Math.abs(touchCurrentX - touchStartX);
    const deltaY = Math.abs(touchCurrentY - touchStartY);
    
    // Only stop auto-scroll if this is primarily horizontal movement
    if (deltaX > deltaY * 1.5 && deltaX > 20) {
        isManualScrolling = true;
    }
}, { passive: true });

// Wheel event for touchpad/mouse wheel - only handle horizontal scrolling
carouselContainer.addEventListener('wheel', (e) => {
    // Only handle horizontal scroll if it's significantly more horizontal than vertical
    if (Math.abs(e.deltaX) > Math.abs(e.deltaY) * 2) {
        // Clear horizontal scroll detected - prevent default and handle manually
        e.preventDefault();
        stopAutoScroll();
        isManualScrolling = true;
        
        updateCurrentIndex();
        
        if (e.deltaX > 30) {
            // Scroll right (threshold to prevent oversensitivity)
            goToSlide(currentIndex + 1, true);
        } else if (e.deltaX < -30) {
            // Scroll left (threshold to prevent oversensitivity)
            goToSlide(currentIndex - 1, true);
        }
        
        setTimeout(() => {
            isManualScrolling = false;
        }, 500);
    }
    // For vertical scrolling or mixed scrolling, let browser handle it naturally
}, { passive: false });

// Arrow key navigation using scrollBy
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault();
        stopAutoScroll();
        isManualScrolling = true;
        
        const slideWidth = getSlideWidth();
        
        if (e.key === 'ArrowLeft') {
            carouselContainer.scrollBy({ left: -slideWidth, behavior: 'smooth' });
        } else if (e.key === 'ArrowRight') {
            carouselContainer.scrollBy({ left: slideWidth, behavior: 'smooth' });
        }
        
        setTimeout(() => {
            isManualScrolling = false;
        }, 500);
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    // Recalculate and maintain current position
    setTimeout(() => {
        goToSlide(currentIndex, false);
    }, 100);
});

// Simple boundary enforcement for scroll-based carousel
function ensureBoundaries() {
    const maxScroll = (slideCount - 1) * getSlideWidth();
    
    if (carouselContainer.scrollLeft < 0) {
        carouselContainer.scrollLeft = 0;
    } else if (carouselContainer.scrollLeft > maxScroll) {
        carouselContainer.scrollLeft = maxScroll;
    }
}

// Initialize carousel
document.addEventListener('DOMContentLoaded', () => {
    // Ensure carousel starts at the beginning
    carouselContainer.scrollLeft = 0;
    lastScrollLeft = 0;
    
    // Set focus so arrow keys work
    carouselContainer.setAttribute('tabindex', '0');
    
    // Start auto-scroll after a brief delay
    setTimeout(() => {
        startAutoScroll();
    }, 1000);
});
</script>

<!-- Content Sections -->
<div class="content-sections">
    <!-- Series Day Section -->
    <section class="content-section">
        <div class="section-header">
            <h2 class="section-title">Series Day</h2>
            <a href="series-day-x.php" class="view-more-btn">View More</a>
        </div>
        <div class="horizontal-scroll">
            <div class="scroll-container">
                <?php
                // Get series day posts from series-day-x category
                $series_category = get_category_by_slug('series-day-x');
                $series_posts = [];
                if ($series_category) {
                    $series_posts = get_posts([
                        'post_type' => 'post',
                        'post_status' => 'publish',
                        'posts_per_page' => 12,
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'cat' => $series_category->term_id
                    ]);
                }
                
                foreach ($series_posts as $post):
                    setup_postdata($post);
                    $content = $post->post_content;
                    $image_url = extract_image_url_from_content($content) ?: get_template_directory_uri().'/assets/default-thumb.jpg';
                    $video_url = extract_video_url_from_content($content) ?: '#';
                    $title = extract_title_from_content($content) ?: get_the_title($post);
                ?>
                <div class="scroll-item">
                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <div class="item-title"><?php echo esc_html($title); ?></div>
                    </a>
                </div>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>

    <!-- Recent Upload Section -->
    <section class="content-section">
        <div class="section-header">
            <h2 class="section-title">Recent Upload</h2>
            <a href="recent upload.php" class="view-more-btn">View More</a>
        </div>
        <div class="horizontal-scroll">
            <div class="scroll-container">
                <?php
                // Get all posts and sort by upload date (same logic as recent upload page)
                $all_posts = get_posts([
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => -1
                ]);
                
                // Sort posts: valid upload_date DESC, then missing/invalid upload_date by post_date DESC
                usort($all_posts, function($a, $b) {
                    $date_a = extract_upload_date_from_excerpt(get_the_excerpt($a));
                    $date_b = extract_upload_date_from_excerpt(get_the_excerpt($b));
                    if ($date_a && $date_b) {
                        return $date_b - $date_a;
                    }
                    if ($date_a && !$date_b) return -1;
                    if (!$date_a && $date_b) return 1;
                    return strtotime($b->post_date) - strtotime($a->post_date);
                });
                
                $recent_posts = array_slice($all_posts, 0, 12);
                
                foreach ($recent_posts as $post):
                    setup_postdata($post);
                    $content = $post->post_content;
                    $image_url = extract_image_url_from_content($content) ?: get_template_directory_uri().'/assets/default-thumb.jpg';
                    $video_url = extract_video_url_from_content($content) ?: '#';
                    $title = extract_title_from_content($content) ?: get_the_title($post);
                ?>
                <div class="scroll-item">
                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <div class="item-title"><?php echo esc_html($title); ?></div>
                    </a>
                </div>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>

    <!-- Most Viewed Section -->
    <section class="content-section">
        <div class="section-header">
            <h2 class="section-title">Most Viewed</h2>
            <a href="most-popular.php" class="view-more-btn">View More</a>
        </div>
        <div class="horizontal-scroll">
            <div class="scroll-container">
                <?php
                // Get most viewed posts
                $all_posts = get_posts([
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => -1
                ]);
                
                // Sort by views
                usort($all_posts, function($a, $b) {
                    $views_a = extract_views_from_content($a->post_content);
                    $views_b = extract_views_from_content($b->post_content);
                    return $views_b - $views_a;
                });
                
                $popular_posts = array_slice($all_posts, 0, 12);
                
                foreach ($popular_posts as $post):
                    setup_postdata($post);
                    $content = $post->post_content;
                    $image_url = extract_image_url_from_content($content) ?: get_template_directory_uri().'/assets/default-thumb.jpg';
                    $video_url = extract_video_url_from_content($content) ?: '#';
                    $title = extract_title_from_content($content) ?: get_the_title($post);
                    $views = extract_views_from_content($content);
                ?>
                <div class="scroll-item">
                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <div class="item-title"><?php echo esc_html($title); ?></div>
                        <div class="item-views"><?php echo number_format($views); ?> views</div>
                    </a>
                </div>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="content-section">
        <div class="section-header">
            <h2 class="section-title">Categories</h2>
            <a href="category.php" class="view-more-btn">View All</a>
        </div>
        <div class="horizontal-scroll">
            <div class="scroll-container">
                <?php
                // Get all categories
                $all_categories = get_categories([
                    'hide_empty' => true,
                    'orderby' => 'name',
                    'order' => 'ASC'
                ]);
                
                foreach ($all_categories as $cat):
                    $image_url = get_stylesheet_directory_uri() . '/images/categories/' . $cat->slug . '.jpg';
                    $image_path = get_stylesheet_directory() . '/images/categories/' . $cat->slug . '.jpg';
                    if (!file_exists($image_path)) {
                        $image_url = get_stylesheet_directory_uri().'/assets/default-category.jpg';
                    }
                ?>
                <div class="scroll-item category-item">
                    <a href="<?php echo esc_url(get_category_link($cat)); ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($cat->name); ?>" loading="lazy">
                        <div class="item-title"><?php echo esc_html($cat->name); ?></div>
                        <div class="item-count"><?php echo $cat->count; ?> posts</div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Random Section -->
    <section class="content-section">
        <div class="section-header">
            <h2 class="section-title">Random</h2>
            <a href="random.php" class="view-more-btn">View More</a>
        </div>
        <div class="horizontal-scroll">
            <div class="scroll-container">
                <?php
                // Get random posts
                $random_posts = get_posts([
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => 12,
                    'orderby' => 'rand'
                ]);
                
                foreach ($random_posts as $post):
                    setup_postdata($post);
                    $content = $post->post_content;
                    $image_url = extract_image_url_from_content($content) ?: get_template_directory_uri().'/assets/default-thumb.jpg';
                    $video_url = extract_video_url_from_content($content) ?: '#';
                    $title = extract_title_from_content($content) ?: get_the_title($post);
                ?>
                <div class="scroll-item">
                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <div class="item-title"><?php echo esc_html($title); ?></div>
                    </a>
                </div>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>
</div>

<script>
// Ultra-responsive smooth horizontal scroll functionality
document.querySelectorAll('.scroll-container').forEach(container => {
    let isDown = false;
    let startX;
    let scrollLeft;
    let velocity = 0;
    let animationId;
    let lastScrollTime = 0;
    let isScrolling = false;

    // Mouse events for desktop
    container.addEventListener('mousedown', (e) => {
        // Only handle mouse drag if clicking directly on the container, not on child elements
        if (e.target === container || e.target.closest('.scroll-item') === null) {
            isDown = true;
            container.style.cursor = 'grabbing';
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
            cancelAnimationFrame(animationId);
            isScrolling = true;
            e.preventDefault();
        }
    });

    container.addEventListener('mouseleave', () => {
        isDown = false;
        container.style.cursor = 'grab';
        isScrolling = false;
    });

    container.addEventListener('mouseup', () => {
        isDown = false;
        container.style.cursor = 'grab';
        isScrolling = false;
    });

    container.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - container.offsetLeft;
        const walk = (x - startX) * 2.5; // More responsive mouse dragging
        container.scrollLeft = scrollLeft - walk;
    });

    // Only handle horizontal scrolling when shift is held - no wheel listener for posts
    // This ensures vertical scrolling works normally everywhere

    // Enhanced touch events for mobile with proper scroll direction detection
    let startTouchX;
    let startTouchY;
    let isTouching = false;
    let lastTouchX;
    let touchVelocity = 0;
    let lastTouchTime = 0;
    let touchStartTime = 0;
    let scrollDirection = null; // 'horizontal', 'vertical', or null
    let hasMovedHorizontally = false;

    container.addEventListener('touchstart', (e) => {
        isTouching = true;
        startTouchX = e.touches[0].clientX;
        startTouchY = e.touches[0].clientY;
        lastTouchX = startTouchX;
        touchVelocity = 0;
        touchStartTime = Date.now();
        cancelAnimationFrame(animationId);
        isScrolling = true;
        scrollDirection = null;
        hasMovedHorizontally = false;
    });

    container.addEventListener('touchmove', (e) => {
        if (!isTouching) return;
        
        const currentTouchX = e.touches[0].clientX;
        const currentTouchY = e.touches[0].clientY;
        const currentTime = Date.now();
        
        const diffX = Math.abs(currentTouchX - startTouchX);
        const diffY = Math.abs(currentTouchY - startTouchY);
        const diff = lastTouchX - currentTouchX;
        const timeDiff = currentTime - lastTouchTime;
        
        // Determine scroll direction only on first significant movement
        if (scrollDirection === null && (diffX > 10 || diffY > 10)) {
            if (diffX > diffY * 1.5) {
                scrollDirection = 'horizontal';
            } else if (diffY > diffX * 1.5) {
                scrollDirection = 'vertical';
            }
        }
        
        // Only handle horizontal scrolling if we've determined it's a horizontal gesture
        if (scrollDirection === 'horizontal') {
            e.preventDefault(); // Only prevent default for horizontal scrolling
            hasMovedHorizontally = true;
            
            if (timeDiff > 0) {
                touchVelocity = diff / timeDiff * 20; // Calculate velocity with higher sensitivity
            }
            
            container.scrollLeft += diff * 2.5; // More responsive touch scrolling
        }
        // For vertical scrolling or undetermined direction, let the browser handle it naturally
        
        lastTouchX = currentTouchX;
        lastTouchTime = currentTime;
    });

    container.addEventListener('touchend', () => {
        isTouching = false;
        isScrolling = false;
        
        // Only apply momentum scrolling if we were doing horizontal scrolling
        if (hasMovedHorizontally && Math.abs(touchVelocity) > 0.5) {
            const momentum = () => {
                container.scrollLeft += touchVelocity;
                touchVelocity *= 0.88; // Faster decay for more responsive feel
                if (Math.abs(touchVelocity) > 0.2) {
                    animationId = requestAnimationFrame(momentum);
                }
            };
            momentum();
        }
        
        // Reset flags
        scrollDirection = null;
        hasMovedHorizontally = false;
    });

    // YouTube-style smooth scroll behavior
    container.style.scrollBehavior = 'smooth';
    container.style.scrollSnapType = 'x proximity';
    
    // Add scroll snap points for better UX
    const items = container.querySelectorAll('.scroll-item');
    items.forEach(item => {
        item.style.scrollSnapAlign = 'start';
    });
    
    // Add CSS for ultra-smooth scrolling with proper scroll chaining
    container.style.overscrollBehavior = 'auto'; // Allow scroll chaining to parent
    container.style.scrollbarWidth = 'none';
    container.style.msOverflowStyle = 'none';
});

// Arrow key support for horizontal scrolling
document.addEventListener('keydown', (e) => {
    const activeContainer = document.querySelector('.scroll-container:hover');
    if (!activeContainer) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        activeContainer.scrollLeft -= 350;
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        activeContainer.scrollLeft += 350;
    }
});

// Auth Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    const authModal = document.getElementById('auth-modal');
    const authBtn = document.getElementById('login-signup-btn');
    const authCloseBtn = document.getElementById('auth-close-btn');
    const authForms = document.querySelectorAll('.auth-form');
    const switchToSignup = document.getElementById('switch-to-signup');
    const switchToLogin = document.getElementById('switch-to-login');

    // Debug: Check if elements exist
    console.log('Auth modal:', authModal);
    console.log('Auth button:', authBtn);
    console.log('Auth close button:', authCloseBtn);

    // Open modal
    if (authBtn) {
        authBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Login button clicked!');
            if (authModal) {
                authModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                console.log('Modal should be visible now');
            }
        });
    }

    // Close modal
    if (authCloseBtn) {
        authCloseBtn.addEventListener('click', () => {
            if (authModal) {
                authModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }

    // Close modal when clicking outside
    if (authModal) {
        authModal.addEventListener('click', (e) => {
            if (e.target === authModal) {
                authModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }

    // Switch to signup form
    if (switchToSignup) {
        switchToSignup.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('signup-form').classList.add('active');
        });
    }

    // Switch to login form
    if (switchToLogin) {
        switchToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('signup-form').classList.remove('active');
            document.getElementById('login-form').classList.add('active');
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', (e) => {
    const authModal = document.getElementById('auth-modal');
    if (e.key === 'Escape' && authModal && authModal.classList.contains('active')) {
        authModal.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Sticky Header Functionality - Always Visible
document.addEventListener('DOMContentLoaded', function() {
    const header = document.getElementById('header');
    
    function updateHeader() {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 100) {
            header.classList.add('sticky');
        } else {
            header.classList.remove('sticky');
        }
    }

    window.addEventListener('scroll', updateHeader);
    
    // Initial check
    updateHeader();
});

// Search Overlay Functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchBar = document.getElementById('search-bar');
    const searchOverlay = document.getElementById('search-overlay');
    const searchOverlayInput = document.getElementById('search-overlay-input');
    const searchOverlayClose = document.getElementById('search-overlay-close');
    const suggestionItems = document.querySelectorAll('.suggestion-item');
    const recentSearchesContainer = document.getElementById('recent-searches');
    const searchResultsContent = document.getElementById('search-results-content');

    // Recent searches functionality
    let recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
    
    function updateRecentSearches() {
        recentSearchesContainer.innerHTML = '';
        recentSearches.slice(0, 5).forEach(search => {
            const span = document.createElement('span');
            span.className = 'suggestion-item';
            span.textContent = search;
            span.addEventListener('click', () => performSearch(search));
            recentSearchesContainer.appendChild(span);
        });
    }
    
    function addToRecentSearches(query) {
        if (query.trim() && !recentSearches.includes(query.trim())) {
            recentSearches.unshift(query.trim());
            recentSearches = recentSearches.slice(0, 10); // Keep only last 10
            localStorage.setItem('recentSearches', JSON.stringify(recentSearches));
            updateRecentSearches();
        }
    }
    
    // Initialize recent searches
    updateRecentSearches();

    // Open search overlay
    function openSearchOverlay() {
        searchOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus input after animation starts
        setTimeout(() => {
            searchOverlayInput.focus();
        }, 100);
    }

    // Close search overlay
    function closeSearchOverlay() {
        searchOverlay.classList.remove('active');
        document.body.style.overflow = '';
        searchOverlayInput.value = '';
        clearSearchResults();
    }

    // Clear search results
    function clearSearchResults() {
        searchResultsContent.innerHTML = '';
    }

    // Perform search
    function performSearch(query) {
        if (!query.trim()) return;
        
        addToRecentSearches(query);
        
        // Show loading state
        searchResultsContent.innerHTML = `
            <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                <div style="font-size: 1.2rem; margin-bottom: 10px;">Searching for "${query}"...</div>
                <div style="width: 40px; height: 40px; border: 3px solid rgba(178, 164, 248, 0.3); border-top: 3px solid #b2a4f8; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            </div>
        `;
        
        // Simulate search delay and redirect (replace with actual search logic)
        setTimeout(() => {
            // For now, we'll redirect to the search page with the query
            window.location.href = `/search?q=${encodeURIComponent(query)}`;
        }, 500);
    }

    // Event listeners
    searchBar.addEventListener('click', function(e) {
        e.preventDefault();
        openSearchOverlay();
    });

    searchOverlayClose.addEventListener('click', closeSearchOverlay);

    // Close on overlay background click
    searchOverlay.addEventListener('click', function(e) {
        if (e.target === searchOverlay) {
            closeSearchOverlay();
        }
    });

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchOverlay.classList.contains('active')) {
            closeSearchOverlay();
        }
    });

    // Search input handling
    searchOverlayInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch(this.value);
        }
    });

    // Real-time search (optional - you can enable this for live search)
    let searchTimeout;
    searchOverlayInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                // Implement live search here if needed
                // For now, we'll just show a preview
                searchResultsContent.innerHTML = `
                    <div style="color: rgba(255, 255, 255, 0.8); padding: 20px; text-align: center;">
                        <div style="margin-bottom: 15px;">Press Enter to search for "${query}"</div>
                        <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.6);">
                            Or click on a suggestion above
                        </div>
                    </div>
                `;
            }, 300);
        } else {
            clearSearchResults();
        }
    });

    // Suggestion items click handlers
    suggestionItems.forEach(item => {
        item.addEventListener('click', function() {
            const query = this.textContent;
            searchOverlayInput.value = query;
            performSearch(query);
        });
    });
});

// Add loading animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<!-- Login/Signup Modal -->
<div id="auth-modal" class="auth-modal">
    <div class="auth-modal-content">
        <button class="auth-close-btn" id="auth-close-btn">&times;</button>
        
        <!-- Login Form -->
                                <form id="login-form" class="auth-form active" method="POST">
                            <input type="hidden" name="action" value="login">
                            <h2 class="auth-title">Login</h2>
                            <?php if (isset($login_error)): ?>
                                <div class="auth-error"><?php echo esc_html($login_error); ?></div>
                            <?php endif; ?>
                            <div class="form-group">
                                <input type="text" id="login-username" name="username" placeholder="Username or Email" required>
                            </div>
                            <div class="form-group">
                                <input type="password" id="login-password" name="password" placeholder="Password" required>
                            </div>
 
                            <button type="submit" class="auth-submit-btn">Login</button>
                            <div class="auth-switch">
                                <p>Don't have an account? <a href="#" id="switch-to-signup">Sign up</a></p>
                            </div>
                        </form>
        
        <!-- Signup Form -->
                                <form id="signup-form" class="auth-form" method="POST">
                            <input type="hidden" name="action" value="signup">
                            <h2 class="auth-title">Sign Up</h2>
                            <?php if (isset($signup_error)): ?>
                                <div class="auth-error"><?php echo esc_html($signup_error); ?></div>
                            <?php endif; ?>
                            <div class="form-group">
                                <input type="text" id="signup-username" name="username" placeholder="Username" required>
                            </div>
                            <div class="form-group">
                                <input type="email" id="signup-email" name="email" placeholder="Email" required>
                            </div>
                            <div class="form-group">
                                <input type="password" id="signup-password" name="password" placeholder="Password" required>
                            </div>
                            <div class="form-group">
                                <input type="password" id="signup-confirm-password" name="confirm_password" placeholder="Confirm Password" required>
                            </div>
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" id="signup-consent" name="consent" required>
                                    <span>I agree to receive promotional emails from our partners</span>
                                </label>
                            </div>
                            <button type="submit" class="auth-submit-btn">Sign Up</button>
                            <div class="auth-switch">
                                <p>Already have an account? <a href="#" id="switch-to-login">Login</a></p>
                            </div>
                        </form>
    </div>
</div>

<!-- Redesigned Footer -->
<div id="footermenuwrap">
    <div class="footer-content">
        <div class="footer-section">
            <h3>Main Pages</h3>
            <a href="/home">Home</a>
            <a href="/most-popular">Most Popular</a>
            <a href="/recent-uploads">Recent Upload</a>
            <a href="/series-day-x">Series Day</a>
            <a href="/category">Categories</a>
            <a href="/random">Random</a>
        </div>
        
        <div class="footer-section">
            <h3><a href="/category" style="color: #b2a4f8; text-decoration: none;">Categories</a></h3>
            <?php
            $footer_categories = get_categories([
                'hide_empty' => true,
                'number' => 6,
                'orderby' => 'count',
                'order' => 'DESC'
            ]);
            foreach ($footer_categories as $cat): ?>
                <a href="<?php echo esc_url(get_category_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="footer-section">
            <h3>Legal</h3>
            <?php foreach($footer_links as $f): ?>
                <a href="<?php echo $f[1]; ?>"><?php echo $f[0]; ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="footer-section">
            <h3>About</h3>
            <a href="/2257-statement">2257 Statement</a>
            <a href="/privacy-policy">Privacy Policy</a>
            <a href="/terms-services">Terms of Service</a>
            <a href="/cookies-policy">Cookies Policy</a>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div id="footerdisclaimer">
            anifinder does not store any files on our server. We only link to the media which is posted on third party website.
        </div>
    </div>
</div>
</body>
</html>
