<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user just logged in
$just_logged_in = false;
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $just_logged_in = true;
    // Unset the flag so warning doesn't show again
    unset($_SESSION['just_logged_in']);
}

// Get user info
$current_user = $_SESSION['username'] ?? 'Guest';
$user_initials = strtoupper(substr($current_user, 0, 2));

// Define base path
$base_url = '/FINANCIALS'; // Adjust this to your actual base path

// Check sidebar state
$sidebar_collapsed = isset($_SESSION['sidebar_collapsed']) ? $_SESSION['sidebar_collapsed'] : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial System</title>
    <!-- SweetAlert2 CSS -->  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- DaisyUI/Tailwind -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background-color: rgba(59, 130, 246, 0.5);
            border-radius: 10px;
        }

        @media (max-width: 767px) {
            .dropdown-content {
                left: 50% !important;
                transform: translateX(-80%) !important;
            }
        }

        /* Timer Styles */
        #sessionTimer {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        #sessionTimer.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            animation: pulse 1s infinite;
        }

        #sessionTimer.danger {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            animation: pulse 0.5s infinite;
        }

        /* Corner Blinking Styles */
        .corner-blink {
            position: fixed;
            width: 100px;
            height: 100px;
            pointer-events: none;
            z-index: 9998;
            display: none;
            opacity: 0;
        }
        
        .top-right-blink {
            top: 0;
            right: 0;
            border-top-right-radius: 10px;
            background: radial-gradient(circle at 100% 0%, rgba(255, 0, 0, 0.8), transparent 70%);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.5);
        }
        
        .top-left-blink {
            top: 0;
            left: 0;
            border-top-left-radius: 10px;
            background: radial-gradient(circle at 0% 0%, rgba(255, 0, 0, 0.8), transparent 70%);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.5);
        }
        
        .bottom-right-blink {
            bottom: 0;
            right: 0;
            border-bottom-right-radius: 10px;
            background: radial-gradient(circle at 100% 100%, rgba(255, 0, 0, 0.8), transparent 70%);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.5);
        }
        
        .bottom-left-blink {
            bottom: 0;
            left: 0;
            border-bottom-left-radius: 10px;
            background: radial-gradient(circle at 0% 100%, rgba(255, 0, 0, 0.8), transparent 70%);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.5);
        }
        
        .corner-blink.active {
            display: block;
            animation: cornerBlink 0.3s infinite alternate;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes cornerBlink {
            0% { opacity: 0.1; }
            100% { opacity: 0.8; }
        }

        /* Loading overlay */
        #logoutLoading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Smooth transitions */
        header {
            transition: all 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Corner Blinking Overlays -->
    <div id="topRightBlink" class="corner-blink top-right-blink"></div>
    <div id="topLeftBlink" class="corner-blink top-left-blink"></div>
    <div id="bottomRightBlink" class="corner-blink bottom-right-blink"></div>
    <div id="bottomLeftBlink" class="corner-blink bottom-left-blink"></div>
    
    <!-- Logout Loading Overlay -->
    <div id="logoutLoading" style="display: none;">
        <div class="spinner"></div>
        <h2 class="text-2xl font-bold mb-4">Logging out...</h2>
        <p class="text-gray-300">Please wait while we end your session securely.</p>
    </div>

    <!-- Navbar -->
    <header class="bg-white shadow-sm z-10 border-b border-gray-200">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Left Side -->
                <div class="flex items-center gap-4">
                    <!-- Mobile Sidebar Toggle -->
                    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-all hover:scale-105 md:hidden mr-4">
                        <i data-lucide="menu" class="w-5 h-5 text-gray-700"></i>
                    </button>

                    <!-- Desktop Sidebar Toggle -->
                    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-all hover:scale-105 hidden md:block mr-4">
                        <i data-lucide="<?php echo $sidebar_collapsed ? 'panel-left-open' : 'panel-left-close'; ?>" class="w-5 h-5 text-gray-700"></i>
                    </button>
                    
                    <!-- Session Timer in Navbar -->
                    <div id="sessionTimerContainer" class="hidden">
                        <div id="sessionTimer" class="flex items-center gap-1">
                            <i data-lucide="clock" class="w-4 h-4"></i>
                            <span id="timerDisplay">02:00</span>
                        </div>
                    </div>
                </div>

                <!-- Right Section -->
                <div class="flex items-center gap-4">
                    <!-- Time Display -->
                    <div class="hidden md:block">
                        <span id="philippineTime" class="font-medium text-sm text-gray-700"></span>
                    </div>

                    <!-- Notification Dropdown -->
                    <div class="dropdown dropdown-end relative">
                        <!-- Bell Button -->
                        <button id="notification-button" tabindex="0"
                            class="p-2 rounded-lg relative hover:scale-105 transition-transform duration-200 hover:bg-gray-100">
                            <i data-lucide="bell" class="w-5 h-5 text-gray-700"></i>
                            <span id="notif-badge"
                                class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
                        </button>

                        <!-- Dropdown Content -->
                        <ul tabindex="0"
                            class="dropdown-content w-80 md:w-96 mt-3 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden transform transition-all duration-200 hidden">
                            
                            <!-- Header -->
                            <li
                                class="px-4 py-3 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white z-10">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="bell" class="w-5 h-5 text-blue-600"></i>
                                    <span class="font-semibold text-gray-900 tracking-wide">Notifications</span>
                                </div>
                                <button
                                    class="text-gray-500 hover:text-gray-900 text-xs flex items-center gap-1 transition-colors duration-150 hover:scale-105">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    <span>Clear All</span>
                                </button>
                            </li>

                            <!-- Notification Items -->
                            <div class="max-h-96 overflow-y-auto px-2 py-2 space-y-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 rounded-lg">
                                <div class="p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-150 border border-gray-200">
                                    <p class="text-sm text-gray-900"><span class="font-semibold text-blue-600">Budget Alert</span>: Marketing department exceeded Q1 budget by 15%.</p>
                                    <span class="text-xs text-gray-500">2 hours ago</span>
                                </div>
                                <div class="p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-150 border border-gray-200">
                                    <p class="text-sm text-gray-900"><span class="font-semibold text-blue-600">New Proposal</span>: Operations submitted a new budget proposal.</p>
                                    <span class="text-xs text-gray-500">1 day ago</span>
                                </div>
                                <div class="p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-150 border border-gray-200">
                                    <p class="text-sm text-gray-900"><span class="font-semibold text-blue-600">Collection Update</span>: ₱250,000 collected from Bohol tour packages.</p>
                                    <span class="text-xs text-gray-500">2 days ago</span>
                                </div>
                            </div>

                            <!-- Footer -->
                            <li
                                class="px-4 py-3 border-t border-gray-200 sticky bottom-0 bg-white text-center">
                                <a href="<?php echo $base_url; ?>/notifications.php"
                                    class="text-blue-600 hover:text-blue-800 text-sm flex items-center justify-center gap-1 transition-colors duration-150 hover:scale-105">
                                    <i data-lucide="list" class="w-4 h-4"></i>
                                    <span>View All Notifications</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- User Profile -->
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-600 to-blue-800 flex items-center justify-center">
                            <span class="text-white text-sm font-medium"><?php echo $user_initials; ?></span>
                        </div>
                        <span class="text-gray-900 font-medium hidden md:block"><?php echo $current_user; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Session Configuration - 2 MINUTES
        const SESSION_TIMEOUT = 120; // 2 minutes = 120 seconds
        const WARNING_SECONDS = 5; // Start warning 5 seconds before timeout
        let sessionTimeLeft = SESSION_TIMEOUT;
        let sessionTimerInterval = null;
        let isSessionActive = true;
        let warningAlertActive = false;
        let tuttSoundInterval = null;
        let cornerBlinkInterval = null;

        // DOM Elements
        const sessionTimerContainer = document.getElementById('sessionTimerContainer');
        const timerDisplay = document.getElementById('timerDisplay');
        const logoutLoading = document.getElementById('logoutLoading');
        const cornerBlinks = [
            document.getElementById('topRightBlink'),
            document.getElementById('topLeftBlink'),
            document.getElementById('bottomRightBlink'),
            document.getElementById('bottomLeftBlink')
        ];

        // Check if user just logged in (from PHP variable)
        const justLoggedIn = <?php echo $just_logged_in ? 'true' : 'false'; ?>;

        // Check if session already expired from previous page load
        let lastActivityTime = localStorage.getItem('lastActivityTime');
        let initialTimeLeft = SESSION_TIMEOUT;
        
        if (lastActivityTime) {
            const now = Date.now();
            const elapsed = Math.floor((now - parseInt(lastActivityTime)) / 1000);
            initialTimeLeft = Math.max(0, SESSION_TIMEOUT - elapsed);
            
            if (initialTimeLeft <= 0) {
                // Session already expired, log out immediately
                setTimeout(() => {
                    logoutUser();
                }, 100);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update Philippine Time
            updatePhilippineTime();
            setInterval(updatePhilippineTime, 1000);

            // Initialize session timeout system with preserved time
            initializeSessionTimeout();
            
            // Show initial warning ONLY if user just logged in
            if (justLoggedIn) {
                setTimeout(showInitialWarning, 500);
            } else {
                // If not just logged in, start timer without showing warning
                startSessionTimer();
                showSessionTimer();
            }

            // Notification dropdown functionality
            const notificationButton = document.getElementById('notification-button');
            const notificationDropdown = document.querySelector('.dropdown-content');

            if (notificationButton && notificationDropdown) {
                notificationButton.addEventListener('click', function() {
                    notificationDropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                        notificationDropdown.classList.add('hidden');
                    }
                });
            }
        });

        // Update Philippine Time
        function updatePhilippineTime() {
            const now = new Date();
            const options = { 
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const phTime = now.toLocaleString('en-PH', options);
            const timeElement = document.getElementById('philippineTime');
            if (timeElement) {
                timeElement.textContent = phTime;
            }
        }

        // Function to update navbar toggle icon
        function updateNavbarToggleIcon() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !isMobileView()) {
                const toggleButtons = document.querySelectorAll('button[onclick="toggleSidebar()"]');
                toggleButtons.forEach(button => {
                    const icon = button.querySelector('i');
                    if (icon) {
                        const isCollapsed = sidebar.classList.contains('w-20');
                        icon.setAttribute('data-lucide', isCollapsed ? 'panel-left-open' : 'panel-left-close');
                    }
                });
                lucide.createIcons();
            }
        }

        function isMobileView() {
            return window.innerWidth < 768; // Tailwind's md breakpoint
        }

        // Toggle sidebar function (called from both sidebar and navbar)
        function toggleSidebar() {
            // Call the toggleSidebar function from sidebar.php
            if (typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
            }
        }

        // Show initial warning message (ONLY AFTER LOGIN)
        function showInitialWarning() {
            Swal.fire({
                title: '⚠️ SESSION TIMEOUT WARNING ⚠️',
                html: `
                    <div class="text-center">
                        <p class="mb-4 text-lg text-gray-700">
                            <strong>Inactivity of user for the next 2 minutes will automatically logout</strong>
                        </p>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <p class="text-red-600 font-semibold mb-2">⚠️ IMPORTANT WARNING:</p>
                            <ul class="text-left text-sm text-red-700 ml-4 list-disc">
                                <li>When 5 seconds remain: CORNER BLINKING RED + "TUTUTT TUTTUT" SOUND EVERY 5 SECONDS</li>
                                <li>You MUST click anywhere to reset the timer</li>
                                <li>No keyboard activity detection - only mouse/touch</li>
                                <li>Session will end automatically after 2 minutes</li>
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: false,
                confirmButtonText: 'I UNDERSTAND',
                confirmButtonColor: '#dc2626',
                allowOutsideClick: false,
                allowEscapeKey: false,
                backdrop: 'rgba(0,0,0,0.7)',
                width: 600,
                customClass: {
                    confirmButton: 'px-8 py-3 text-lg font-bold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    startSessionTimer();
                    showSessionTimer();
                }
            });
        }

        // Initialize session timeout system
        function initializeSessionTimeout() {
            // Set initial time left from localStorage
            sessionTimeLeft = initialTimeLeft;
            
            // Set up activity event listeners (NO KEYBOARD)
            const activityEvents = [
                'mousemove', 'click', 'scroll', 
                'touchstart', 'mousedown'
            ];

            activityEvents.forEach(event => {
                document.addEventListener(event, handleUserActivity, { passive: true });
            });
        }

        // Handle user activity
        function handleUserActivity() {
            if (!isSessionActive) return;
            
            // Save current time to localStorage
            localStorage.setItem('lastActivityTime', Date.now());
            
            // Reset warning alert if active
            if (warningAlertActive) {
                stopWarningAlert();
            }
            
            // Reset the session timer
            resetSessionTimer();
            
            // Visual feedback for timer reset
            const timerElement = document.getElementById('sessionTimer');
            if (timerElement) {
                timerElement.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    timerElement.style.transform = 'scale(1)';
                }, 300);
            }
        }

        // Start session timer
        function startSessionTimer() {
            // Clear existing interval
            if (sessionTimerInterval) {
                clearInterval(sessionTimerInterval);
            }
            
            // Update timer display with current time left
            updateTimerDisplay();
            
            // Start countdown
            sessionTimerInterval = setInterval(() => {
                sessionTimeLeft--;
                updateTimerDisplay();
                
                // Check for warning alert (5 seconds left)
                if (sessionTimeLeft <= WARNING_SECONDS && sessionTimeLeft > 0) {
                    if (!warningAlertActive) {
                        startWarningAlert();
                    }
                }
                
                // Check if session expired
                if (sessionTimeLeft <= 0) {
                    clearInterval(sessionTimerInterval);
                    sessionExpired();
                }
            }, 1000);
        }

        // Reset session timer
        function resetSessionTimer() {
            sessionTimeLeft = SESSION_TIMEOUT;
            updateTimerDisplay();
            updateTimerVisualState();
        }

        // Update timer display
        function updateTimerDisplay() {
            const minutes = Math.floor(sessionTimeLeft / 60);
            const seconds = sessionTimeLeft % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Update timer display
            if (timerDisplay) {
                timerDisplay.textContent = timeString;
            }
            
            // Update visual state
            updateTimerVisualState();
        }

        // Update timer visual state
        function updateTimerVisualState() {
            const timerElement = document.getElementById('sessionTimer');
            if (!timerElement) return;
            
            if (sessionTimeLeft <= WARNING_SECONDS) {
                // Warning alert state
                timerElement.classList.remove('warning');
                timerElement.classList.add('danger');
                timerElement.style.background = 'linear-gradient(135deg, #ff0000 0%, #990000 100%)';
                timerElement.style.animation = 'pulse 0.3s infinite';
            } else if (sessionTimeLeft <= 30) {
                // Warning state (30 seconds left)
                timerElement.classList.remove('danger');
                timerElement.classList.add('warning');
                timerElement.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                timerElement.style.animation = 'pulse 1s infinite';
            } else {
                // Normal state
                timerElement.classList.remove('warning', 'danger');
                timerElement.style.background = 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
                timerElement.style.animation = 'none';
            }
        }

        // Show session timer
        function showSessionTimer() {
            if (sessionTimerContainer) {
                sessionTimerContainer.classList.remove('hidden');
                sessionTimerContainer.classList.add('animate-fadeIn');
            }
        }

        // Start warning alert (5 seconds left)
        function startWarningAlert() {
            warningAlertActive = true;
            
            // Start corner blinking
            startCornerBlinking();
            
            // Play "tututt tuttut" sound immediately and then every 5 seconds
            playTututtTuttutSound();
            
            // Set interval for "tututt tuttut" sound every 5 seconds
            tuttSoundInterval = setInterval(playTututtTuttutSound, 5000);
            
            // Show warning alert
            Swal.fire({
                title: '⚠️ WARNING! ⚠️',
                html: `
                    <div class="text-center py-4">
                        <div class="text-4xl mb-4">⏰</div>
                        <p class="text-xl font-bold text-red-600 mb-2">
                            CRITICAL WARNING: ${sessionTimeLeft} SECONDS REMAINING!
                        </p>
                        <p class="text-lg text-gray-700">
                            Click anywhere on screen to reset timer!
                        </p>
                        <p class="text-sm text-gray-500 mt-4">
                            Automatic logout in: <span id="swalCountdown" class="font-bold">${sessionTimeLeft}</span> seconds
                        </p>
                    </div>
                `,
                icon: 'error',
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                backdrop: 'rgba(255,0,0,0.3)',
                width: 500,
                timer: sessionTimeLeft * 1000,
                timerProgressBar: true,
                didOpen: () => {
                    // Update countdown in SweetAlert
                    const countdownElement = document.getElementById('swalCountdown');
                    const updateCountdown = () => {
                        if (countdownElement && warningAlertActive) {
                            countdownElement.textContent = sessionTimeLeft;
                            setTimeout(updateCountdown, 1000);
                        }
                    };
                    updateCountdown();
                },
                willClose: () => {
                    // If SweetAlert closes and warning alert is still active, session expired
                    if (warningAlertActive && sessionTimeLeft <= 0) {
                        sessionExpired();
                    }
                }
            });
        }

        // Start corner blinking
        function startCornerBlinking() {
            // Clear existing interval
            if (cornerBlinkInterval) {
                clearInterval(cornerBlinkInterval);
            }
            
            // Activate all corners
            cornerBlinks.forEach(blink => {
                blink.classList.add('active');
            });
            
            // Rotate active corners for visual effect
            let activeIndex = 0;
            cornerBlinkInterval = setInterval(() => {
                // Turn off all corners
                cornerBlinks.forEach(blink => {
                    blink.classList.remove('active');
                });
                
                // Turn on one corner at a time in sequence
                cornerBlinks[activeIndex].classList.add('active');
                
                // Move to next corner
                activeIndex = (activeIndex + 1) % cornerBlinks.length;
            }, 300);
        }

        // Stop corner blinking
        function stopCornerBlinking() {
            if (cornerBlinkInterval) {
                clearInterval(cornerBlinkInterval);
                cornerBlinkInterval = null;
            }
            
            cornerBlinks.forEach(blink => {
                blink.classList.remove('active');
            });
        }

        // Play "tututt tuttut" sound pattern
        function playTututtTuttutSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // "tututt" pattern: short, short, long, short, short
                // "tuttut" pattern: short, long, short, short, long
                
                const playBeep = (frequency, duration, startTime) => {
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(frequency, startTime);
                    
                    gainNode.gain.setValueAtTime(0.3, startTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
                    
                    oscillator.start(startTime);
                    oscillator.stop(startTime + duration);
                    
                    return startTime + duration;
                };
                
                let currentTime = audioContext.currentTime;
                
                // First "tu" (short)
                currentTime = playBeep(800, 0.1, currentTime);
                currentTime += 0.05; // Small gap
                
                // Second "tu" (short)
                currentTime = playBeep(800, 0.1, currentTime);
                currentTime += 0.1; // Slightly longer gap
                
                // "tut" (long)
                currentTime = playBeep(600, 0.3, currentTime);
                currentTime += 0.2; // Gap between patterns
                
                // First "tu" (short)
                currentTime = playBeep(800, 0.1, currentTime);
                currentTime += 0.05; // Small gap
                
                // Second "tu" (short)
                currentTime = playBeep(800, 0.1, currentTime);
                currentTime += 0.1; // Slightly longer gap
                
                // "tut" (long)
                currentTime = playBeep(600, 0.3, currentTime);
                
                // Close audio context after sound plays
                setTimeout(() => {
                    audioContext.close();
                }, 1000);
                
            } catch (e) {
                console.log('Audio context not supported:', e);
            }
        }

        // Stop warning alert
        function stopWarningAlert() {
            warningAlertActive = false;
            
            // Stop corner blinking
            stopCornerBlinking();
            
            // Clear the "tututt tuttut" sound interval
            if (tuttSoundInterval) {
                clearInterval(tuttSoundInterval);
                tuttSoundInterval = null;
            }
            
            // Close any open SweetAlert (the warning)
            Swal.close();
        }

        // Session expired handler
        function sessionExpired() {
            isSessionActive = false;
            
            // Clear localStorage
            localStorage.removeItem('lastActivityTime');
            
            // Stop warning alert if active
            if (warningAlertActive) {
                stopWarningAlert();
            }
            
            // Clear timer
            if (sessionTimerInterval) {
                clearInterval(sessionTimerInterval);
            }
            
            // Show session expired message with 5 second countdown
            Swal.fire({
                title: '⏰ SESSION EXPIRED ⏰',
                html: `
                    <div class="text-center py-4">
                        <div class="text-6xl mb-4">🚨</div>
                        <p class="text-xl font-bold text-gray-700 mb-2">
                            You have been inactive for 2 minutes
                        </p>
                        <p class="text-lg text-red-600">
                            For security reasons, your session has expired
                        </p>
                        <p class="text-sm text-gray-500 mt-4">
                            Automatically logging out in <span id="logoutCountdown">5</span> seconds...
                        </p>
                    </div>
                `,
                icon: 'warning',
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                backdrop: 'rgba(0,0,0,0.8)',
                timer: 5000,
                timerProgressBar: true,
                didOpen: () => {
                    // Start countdown display
                    let countdown = 5;
                    const countdownElement = document.getElementById('logoutCountdown');
                    const countdownInterval = setInterval(() => {
                        countdown--;
                        if (countdownElement) {
                            countdownElement.textContent = countdown;
                        }
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                        }
                    }, 1000);
                },
                willClose: () => {
                    logoutUser();
                }
            });
        }

        // Logout user and redirect to logout.php
        function logoutUser() {
            // Show loading overlay
            logoutLoading.style.display = 'flex';
            
            // Wait 2 seconds then redirect to logout.php
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>/USM/logout.php';
            }, 2000);
        }

        // Keyboard shortcut for sidebar (B key with Ctrl/Cmd)
        document.addEventListener('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'b') {
                event.preventDefault();
                toggleSidebar();
            }
        });

        // Update navbar toggle icon on resize
        window.addEventListener('resize', function() {
            updateNavbarToggleIcon();
        });

        // Initial update of navbar toggle icon
        setTimeout(updateNavbarToggleIcon, 100);

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (sessionTimerInterval) {
                clearInterval(sessionTimerInterval);
            }
            if (tuttSoundInterval) {
                clearInterval(tuttSoundInterval);
            }
            if (cornerBlinkInterval) {
                clearInterval(cornerBlinkInterval);
            }
        });
    </script>
</body>
</html>
