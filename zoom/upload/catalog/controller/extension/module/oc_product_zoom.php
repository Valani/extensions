<?php
// File: upload/catalog/controller/extension/module/oc_product_zoom.php
class ControllerExtensionModuleOcProductZoom extends Controller {
    public function injectZoom(&$route, &$data, &$output) {
        // Only proceed if the module is enabled
        if (!$this->config->get('module_oc_product_zoom_status')) {
            return;
        }

        // Configuration options from admin settings
        $zoom_factor = $this->config->get('module_oc_product_zoom_factor') ?: 2;
        $zoom_width = $this->config->get('module_oc_product_zoom_width') ?: 200;
        $zoom_height = $this->config->get('module_oc_product_zoom_height') ?: 200;
        $border_color = $this->config->get('module_oc_product_zoom_border_color') ?: '#999';

        // Add CSS for zoom functionality
        $css = '<style>
            .sc-product-images-slide {
                position: relative;
                overflow: visible !important;
            }
            
            .sc-product-images-slide img {
                max-width: 100%;
                height: auto;
                cursor: crosshair;
            }
            
            .product-zoom-magnifier {
                position: absolute;
                width: ' . $zoom_width . 'px;
                height: ' . $zoom_height . 'px;
                border-radius: 50%;
                border: 2px solid ' . $border_color . ';
                pointer-events: none;
                background-repeat: no-repeat;
                opacity: 0;
                transition: opacity 0.2s;
                z-index: 1000;
                box-shadow: 0 0 10px rgba(0,0,0,0.3);
            }

            @media (max-width: 768px) {
                .product-zoom-magnifier {
                    width: 150px;
                    height: 150px;
                }
            }
        </style>';

        // Add JS for zoom functionality
        $js = '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize zoom for main image
            initProductZoom();
            
            // For Swiper navigation, reinitialize when slides change
            if (typeof mainImagesSwiper !== "undefined") {
                mainImagesSwiper.on("slideChangeTransitionEnd", function() {
                    initProductZoom();
                });
            }
            
            function initProductZoom() {
                const imageSlides = document.querySelectorAll(".sc-product-images-slide");
                
                // Remove existing magnifiers
                document.querySelectorAll(".product-zoom-magnifier").forEach(el => el.remove());
                
                // Initialize each slide
                imageSlides.forEach(slide => {
                    const img = slide.querySelector("img");
                    if (!img) return;
                    
                    // Create magnifier element
                    const magnifier = document.createElement("div");
                    magnifier.className = "product-zoom-magnifier";
                    slide.appendChild(magnifier);
                    
                    // Zoom factor from admin settings
                    const zoomFactor = ' . $zoom_factor . ';
                    
                    // Throttle function to limit executions
                    function throttle(func, delay) {
                        let lastCall = 0;
                        return function(...args) {
                            const now = Date.now();
                            if (now - lastCall >= delay) {
                                lastCall = now;
                                func.apply(this, args);
                            }
                        };
                    }
                    
                    // Update magnifier position and content
                    function updateMagnifier(x, y) {
                        // Image dimensions
                        const imgWidth = img.offsetWidth;
                        const imgHeight = img.offsetHeight;
                        
                        // Magnifier dimensions
                        const magWidth = magnifier.offsetWidth;
                        const magHeight = magnifier.offsetHeight;
                        
                        // Get mouse coordinates relative to image
                        const rect = img.getBoundingClientRect();
                        
                        // Check if cursor is over the image
                        if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                            magnifier.style.opacity = "0";
                            return;
                        }
                        
                        // Local coordinates relative to image
                        const localX = x - rect.left;
                        const localY = y - rect.top;
                        
                        // Show magnifier
                        magnifier.style.opacity = "1";
                        
                        // Position magnifier
                        let magX = localX - magWidth / 2;
                        let magY = localY - magHeight / 2;
                        
                        magnifier.style.left = magX + "px";
                        magnifier.style.top = magY + "px";
                        
                        // Set background for magnifier (zoomed image)
                        magnifier.style.backgroundImage = `url(${img.src})`;
                        magnifier.style.backgroundSize = `${imgWidth * zoomFactor}px ${imgHeight * zoomFactor}px`;
                        magnifier.style.backgroundPosition = `-${localX * zoomFactor - magWidth / 2}px -${localY * zoomFactor - magHeight / 2}px`;
                    }
                    
                    // Throttled update function for mouse movement
                    const throttledUpdate = throttle(function(e) {
                        updateMagnifier(e.clientX, e.clientY);
                    }, 16); // ~60fps
                    
                    // Mouse movement over image
                    slide.addEventListener("mousemove", throttledUpdate);
                    
                    // Hide magnifier when mouse leaves image
                    slide.addEventListener("mouseleave", function() {
                        magnifier.style.opacity = "0";
                    });
                    
                    // Touch device support
                    slide.addEventListener("touchmove", function(e) {
                        e.preventDefault(); // Prevent page scrolling
                        const touch = e.touches[0];
                        updateMagnifier(touch.clientX, touch.clientY);
                    });
                    
                    slide.addEventListener("touchstart", function(e) {
                        const touch = e.touches[0];
                        updateMagnifier(touch.clientX, touch.clientY);
                    });
                    
                    slide.addEventListener("touchend", function() {
                        magnifier.style.opacity = "0";
                    });
                    
                    // Fix for fancybox integration - disable zoom when clicking
                    slide.querySelector(".oct-gallery").addEventListener("click", function() {
                        magnifier.style.opacity = "0";
                    });
                });
            }
        });
        </script>';

        // Insert our CSS and JS before the closing </body> tag
        $output = str_replace('</body>', $css . $js . '</body>', $output);
    }
}