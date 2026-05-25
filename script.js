document.addEventListener('DOMContentLoaded', function() {
    // ===== МОБИЛЬНОЕ МЕНЮ =====
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mobileMenu = document.querySelector('.mobile-menu');
    const mobileMenuClose = document.querySelector('.mobile-menu-close');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            if (mobileMenu) mobileMenu.classList.add('active');
        });
    }
    
    if (mobileMenuClose) {
        mobileMenuClose.addEventListener('click', function() {
            if (mobileMenu) mobileMenu.classList.remove('active');
        });
    }
    
    document.querySelectorAll('.mobile-dropdown > a').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.querySelector('.mobile-submenu').classList.toggle('active');
        });
    });
    
    // ===== СЛАЙДЕР =====
    const sliderTrack = document.querySelector('.slider-track');
    const slides = document.querySelectorAll('.slide');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const dots = document.querySelectorAll('.dot');
    
    if (sliderTrack && slides.length) {
        let currentSlide = 0;
        const totalSlides = slides.length;
        
        function updateSlider() {
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                currentSlide = (currentSlide + 1) % totalSlides;
                updateSlider();
            });
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                updateSlider();
            });
        }
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', function() {
                currentSlide = index;
                updateSlider();
            });
        });
        
        let autoSlide = setInterval(() => {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        }, 5000);
        
        const slider = document.querySelector('.slider');
        if (slider) {
            slider.addEventListener('mouseenter', () => clearInterval(autoSlide));
            slider.addEventListener('mouseleave', () => {
                autoSlide = setInterval(() => {
                    currentSlide = (currentSlide + 1) % totalSlides;
                    updateSlider();
                }, 5000);
            });
        }
    }
    
    // ===== ВИДЕО =====
    const video = document.getElementById('header-video');
    if (video) {
        video.play().catch(e => console.log('Автовоспроизведение заблокировано'));
    }
    
    // ===== ПЛАВНАЯ ПРОКРУТКА =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
                if (mobileMenu) mobileMenu.classList.remove('active');
            }
        });
    });
    
    // ===== ФОРМА ОБРАТНОЙ СВЯЗИ =====
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Спасибо! Мы свяжемся с вами.');
            this.reset();
        });
    }
});