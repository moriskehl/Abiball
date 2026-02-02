/**
 * Shooting Stars Animation - Dark Mode Only
 * Creates subtle shooting stars at random positions
 */
(function () {
    'use strict';

    // Only run in dark mode
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    // Star positions (matching the CSS star layers roughly)
    const starPositions = [
        { x: 10, y: 15 }, { x: 25, y: 8 }, { x: 40, y: 22 }, { x: 55, y: 12 },
        { x: 70, y: 28 }, { x: 85, y: 18 }, { x: 15, y: 35 }, { x: 30, y: 42 },
        { x: 50, y: 38 }, { x: 65, y: 45 }, { x: 80, y: 32 }, { x: 20, y: 55 },
        { x: 45, y: 52 }, { x: 60, y: 58 }, { x: 75, y: 48 }, { x: 35, y: 65 },
        { x: 90, y: 60 }, { x: 12, y: 72 }, { x: 28, y: 78 }, { x: 52, y: 68 },
        { x: 68, y: 75 }, { x: 82, y: 70 }, { x: 5, y: 85 }, { x: 38, y: 82 },
        { x: 58, y: 88 }, { x: 78, y: 92 }, { x: 92, y: 25 }, { x: 8, y: 48 }
    ];

    function createShootingStar() {
        if (!isDarkMode()) return;

        // Pick a random star position
        const pos = starPositions[Math.floor(Math.random() * starPositions.length)];

        // Random movement direction (angle in degrees)
        const angle = Math.random() * 360;
        const duration = 1.2 + Math.random() * 0.8; // 1.2-2 seconds
        const length = 50 + Math.random() * 30; // 50-80px trail
        const distance = 150 + Math.random() * 100; // 150-250px travel

        // Calculate movement vector
        const radians = angle * (Math.PI / 180);
        const moveX = Math.cos(radians) * distance;
        const moveY = Math.sin(radians) * distance;

        // Create the shooting star element
        // The gradient goes: transparent (back) -> white (front/head)
        // We rotate the element so the "front" (right side) points in the movement direction
        const star = document.createElement('div');
        star.className = 'js-shooting-star';
        star.style.cssText = `
      position: fixed;
      left: ${pos.x}%;
      top: ${pos.y}%;
      width: ${length}px;
      height: 1px;
      background: linear-gradient(90deg, 
        rgba(255, 255, 255, 0) 0%, 
        rgba(255, 255, 255, 0.2) 40%, 
        rgba(255, 255, 255, 0.7) 100%);
      border-radius: 999px;
      transform: rotate(${angle}deg);
      transform-origin: right center;
      pointer-events: none;
      z-index: 0;
      opacity: 0;
    `;

        // Add the bright head at the front (right side = direction of movement)
        const head = document.createElement('div');
        head.style.cssText = `
      position: absolute;
      right: -1px;
      top: 50%;
      transform: translateY(-50%);
      width: 2px;
      height: 2px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 0 4px 1px rgba(255, 255, 255, 0.8);
    `;
        star.appendChild(head);

        document.body.appendChild(star);

        // Animate - the star moves in the direction it's pointing
        star.animate([
            {
                opacity: 0,
                transform: `rotate(${angle}deg) translateX(0)`
            },
            {
                opacity: 1,
                transform: `rotate(${angle}deg) translateX(${distance * 0.1}px)`,
                offset: 0.1
            },
            {
                opacity: 0.6,
                transform: `rotate(${angle}deg) translateX(${distance * 0.7}px)`,
                offset: 0.8
            },
            {
                opacity: 0,
                transform: `rotate(${angle}deg) translateX(${distance}px)`
            }
        ], {
            duration: duration * 1000,
            easing: 'ease-out',
            fill: 'forwards'
        }).onfinish = () => star.remove();
    }

    function scheduleNextStar() {
        // Random delay between 3-8 seconds
        const delay = 3000 + Math.random() * 5000;
        setTimeout(() => {
            createShootingStar();
            scheduleNextStar();
        }, delay);
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(scheduleNextStar, 2000);
        });
    } else {
        setTimeout(scheduleNextStar, 2000);
    }
})();
