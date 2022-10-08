(function(){
    'use strict';

    let initialized = false;

    const init = () => {
        if (initialized) {
            return;
        }

        initialized = true;

        let drawing = false;
        let startPos;
        let part;

        const image = preview.querySelector('img');
        const widget = preview.closest('.widget');

        widget.classList.add('widget--preview');
        
        const wrapper = document.createElement('div');
        wrapper.classList.add('tl_edit_preview_wrapper');
        wrapper.classList.add('tl_edit_preview_enabled');

        widget.insertBefore(wrapper, preview);
        wrapper.appendChild(preview);

        const start = (e) => {
            if (drawing) {
                return true;
            }

            if (typeof part !== 'undefined') {
                if (e.target.matches('.tl_edit_preview_important_part')) {
                    return true;
                }

                part.remove();
            }

            e.preventDefault();

            drawing = true;

            const previewRect = preview.getBoundingClientRect();
            const imageRect = image.getBoundingClientRect();

            console.debug(previewRect.left - imageRect.left);
            console.debug(previewRect.top - imageRect.top);

            startPos = {
                x: Math.max(e.clientX - previewRect.left, 0),
                y: Math.max(e.clientY - previewRect.top, 0)
            };

            part = document.createElement('div');
            part.classList.add('tl_edit_preview_important_part');
            preview.appendChild(part);

            move(e);
        };

        const move = (e) => {
            if (!drawing) {
                return true;
            }

            e.preventDefault();

            const imageSize = image.getBoundingClientRect();
            const rect = {
                x: [
                    Math.max(0, Math.min(imageSize.width, startPos.x)),
                    Math.max(0, Math.min(imageSize.width, e.clientX - preview.getBoundingClientRect().left))
                ],
                y: [
                    Math.max(0, Math.min(imageSize.height, startPos.y)),
                    Math.max(0, Math.min(imageSize.height, e.clientY - preview.getBoundingClientRect().top))
                ]
            };

            part.style.top = Math.min(rect.y[0], rect.y[1]) + 'px';
            part.style.left = Math.min(rect.x[0], rect.x[1]) + 'px';
            part.style.width = Math.abs(rect.x[0] - rect.x[1]) + 'px';
            part.style.height = Math.abs(rect.y[0] - rect.y[1]) + 'px';
        };

        const stop = (e) => {
            if (!drawing) {
                return true;
            }

            move(e);
            drawing = false;

            const partRect = part.getBoundingClientRect();

            console.debug(partRect);

            if (partRect.width <= 0.001 || partRect.height <= 0.001) {
                part.remove();
                return;
            }

            interact(part)
                .resizable({
                    edges: { top: true, left: true, bottom: true, right: true },
                    listeners: {
                        move: (event) => {
                            let x = parseFloat(part.style.left.replace('px', ''));
                            let y = parseFloat(part.style.top.replace('px', ''));
                    
                            x += event.deltaRect.left
                            y += event.deltaRect.top
                    
                            Object.assign(event.target.style, {
                                width: `${event.rect.width}px`,
                                height: `${event.rect.height}px`,
                                left: `${x}px`,
                                top: `${y}px`
                            })
                    
                            Object.assign(event.target.dataset, { x, y })
                        }
                    },
                    modifiers: [
                        interact.modifiers.restrictRect({ restriction: image }),
                    ],
                })
                .draggable({
                    listeners: {
                        move: (event) => {
                            let x = parseFloat(part.style.left.replace('px', ''));
                            let y = parseFloat(part.style.top.replace('px', ''));

                            x += event.dx;
                            y += event.dy;

                            event.target.style.left = `${x}px`;
                            event.target.style.top = `${y}px`;
                        },
                    },
                    modifiers: [
                        interact.modifiers.restrictRect({ restriction: image }),
                    ],
                })
            ;
        };

        const updateValues = () => {

        };

        const updateImage = () => {
            
        };

        const getCurrentImportantParts = () => {
            const input = document.getElementById('ctrl_importantParts');

            if (null === input) {
                return {};
            }

            return JSON.parse(input.value);
        };

        const getCurrentAlternative = () => {
            const select = document.querySelector('select[name="alternative-selection"]');

            if (null === select) {
                return 'default';
            }

            return select.value;
        };

        wrapper.addEventListener('mousedown', start);
        wrapper.addEventListener('touchstart', start);

        document.addEventListener('mousemove', move);
        document.addEventListener('touchmove', move);

        document.addEventListener('mouseup', stop);
        document.addEventListener('touchend', stop);
        document.addEventListener('touchcancel', stop);
    };

    if (null !== document.getElementById('ctrl_importantParts')) {
        init();
    }

    new MutationObserver(function (mutationsList) {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (element) {
                    if (element.matches && element.matches('#ctrl_importantParts')) {
                        init();
                    }
                })
            }
        }
    }).observe(document, {
        attributes: false,
        childList: true,
        subtree: true
    });
})();
