(function () {
    document.querySelectorAll('.top-bar-rotator').forEach((rotator) => {
        const items = rotator.querySelectorAll('.top-bar-rotator-item');
        if (items.length < 2) {
            return;
        }

        let index = 0;
        const interval = parseInt(rotator.dataset.interval || '6000', 10);

        window.setInterval(() => {
            items[index].classList.remove('is-active');
            index = (index + 1) % items.length;
            items[index].classList.add('is-active');
        }, interval);
    });
})();
