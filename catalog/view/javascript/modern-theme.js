/**
 * AI Store — Modern Theme Animations & Interactions
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initStickyHeader();
    initScrollAnimations();
    initProductStagger();
    initCartPulse();
    initCounterAnimation();
  });

  function initStickyHeader() {
    var headerWrap = document.querySelector('.ai-header-wrap');
    if (!headerWrap) return;

    var lastScroll = 0;
    window.addEventListener('scroll', function () {
      var currentScroll = window.pageYOffset;
      if (currentScroll > 50) {
        headerWrap.classList.add('scrolled');
      } else {
        headerWrap.classList.remove('scrolled');
      }
      lastScroll = currentScroll;
    }, { passive: true });
  }

  function initScrollAnimations() {
    var elements = document.querySelectorAll('.ai-animate, .ai-section, .ai-feature-card, .product-thumb, .ai-section-header');
    if (!elements.length) return;

    elements.forEach(function (el) {
      if (!el.classList.contains('ai-animate')) {
        el.classList.add('ai-animate');
      }
    });

    if (!('IntersectionObserver' in window)) {
      elements.forEach(function (el) { el.classList.add('ai-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('ai-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    elements.forEach(function (el) { observer.observe(el); });
  }

  function initProductStagger() {
    var products = document.querySelectorAll('.product-thumb');
    products.forEach(function (product, index) {
      var delay = Math.min(index % 8, 4);
      product.classList.add('ai-animate-delay-' + (delay + 1));
    });
  }

  function initCartPulse() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.product-thumb .button button[formaction]');
      if (!btn) return;

      var cartBtn = document.querySelector('#cart .btn-lg');
      if (cartBtn) {
        cartBtn.style.transform = 'scale(1.1)';
        setTimeout(function () {
          cartBtn.style.transform = '';
        }, 300);
      }
    });
  }

  function initCounterAnimation() {
    var counters = document.querySelectorAll('.ai-counter');
    if (!counters.length || !('IntersectionObserver' in window)) return;

    var counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;

        var el = entry.target;
        var target = parseInt(el.getAttribute('data-target'), 10);
        var suffix = el.getAttribute('data-suffix') || '';
        var duration = 2000;
        var start = 0;
        var startTime = null;

        function animate(timestamp) {
          if (!startTime) startTime = timestamp;
          var progress = Math.min((timestamp - startTime) / duration, 1);
          var eased = 1 - Math.pow(1 - progress, 3);
          el.textContent = Math.floor(eased * target) + suffix;
          if (progress < 1) {
            requestAnimationFrame(animate);
          }
        }

        requestAnimationFrame(animate);
        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });

    counters.forEach(function (c) { counterObserver.observe(c); });
  }
})();
