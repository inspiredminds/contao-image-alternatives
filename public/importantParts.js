(function(){
    'use strict';

    let mainInitialized = false;
    let importantPartsInput = null;

    const getImportantPartData = (alternative) => {
        if (null === importantPartsInput) {
            return {};
        }

        const importantParts = JSON.parse(importantPartsInput.value);

        if (typeof importantParts[alternative] === 'undefined') {
            return {};
        }

        return importantParts[alternative];
    };

    const updateImportantPartData = (alternative, data) => {
        if (null === importantPartsInput) {
            return;
        }

        let importantParts = JSON.parse(importantPartsInput.value);
        importantParts[alternative] = data;
        importantPartsInput.value = JSON.stringify(importantParts, null, 4);
    };

    const getCurrentAlternative = () => {
        const select = document.querySelector('select[name="importantPartSwitch"]');

        if (null === select) {
            return 'default';
        }

        return select.value;
    };

    const editPreviewWizard = () => {
        let el = document.querySelector('.tl_edit_preview'),
		    imageElement = el.querySelector('img'),
			inputElements = {},
			isDrawing = false,
			partElement, startPos,
            getComputedSize = function() {
                let style = getComputedStyle(imageElement);
                let paddingLeft = parseFloat(style['padding-left']);
                let paddingRight = parseFloat(style['padding-right']);
                let paddingTop = parseFloat(style['padding-top']);
                let paddingBottom = parseFloat(style['padding-bottom']);
                return {
                    width: imageElement.clientWidth - paddingLeft - paddingRight,
                    height: imageElement.clientHeight - paddingTop - paddingBottom,
                    computedTop: paddingTop,
                    computedLeft: paddingLeft
                }
            },
			getScale = function() {
                let size = getComputedSize();
				return {
					x: size.width,
					y: size.height,
				};
			},
			updateImage = function() {
				var scale = getScale(),
					imageSize = getComputedSize();
				partElement.setStyles({
					top: imageSize.computedTop + Math.round(inputElements.y * scale.y) + 'px',
					left: imageSize.computedLeft + Math.round(inputElements.x * scale.x) + 'px',
					width: Math.round(inputElements.width * scale.x) + 'px',
					height: Math.round(inputElements.height * scale.y) + 'px'
				});
				if (!parseFloat(inputElements.width) || !parseFloat(inputElements.height)) {
                    partElement.style.display = 'none';
				} else {
                    partElement.style.removeProperty('display')
				}
			},
			updateValues = function() {
				var scale = getScale(),
					styles = {
                        top: partElement.style.top,
                        left: partElement.style.left,
                        width: partElement.style.width,
                        height: partElement.style.height,
                    },
					imageSize = getComputedSize(),
					values = {
						x: Math.max(0, Math.min(1, (parseFloat(styles.left) - imageSize.computedLeft) / scale.x)),
						y: Math.max(0, Math.min(1, (parseFloat(styles.top) - imageSize.computedTop) / scale.y))
					};
				values.width = Math.min(1 - values.x, styles.width.toFloat() / scale.x);
				values.height = Math.min(1 - values.y, styles.height.toFloat() / scale.y);
				if (!values.width || !values.height) {
					values.x = values.y = values.width = values.height = 0;
					partElement.style.display = 'none';
				} else {
					partElement.style.removeProperty('display')
				}
				Object.each(values, function(value, key) {
					inputElements[key] = parseFloat(value.toFixed(15));
				});
                updateImportantPartData(getCurrentAlternative(), inputElements);
			},
			start = function(event) {
				event.preventDefault();
				if (isDrawing) {
					return;
				}
				isDrawing = true;
                var imageSize = getComputedSize();
				startPos = {
					x: event.clientX - el.getBoundingClientRect().x - imageSize.computedLeft,
					y: event.clientY - el.getBoundingClientRect().y - imageSize.computedTop
				};
				move(event);
			},
			move = function(event) {
				if (!isDrawing) {
					return;
				}
				event.preventDefault();
				var imageSize = getComputedSize();
                var aspectRatioSelect = document.querySelector('select[name="aspectRatioSwitch"]');
                var aspectRatio = null;

                if (null !== aspectRatioSelect) {
                    var aspectRatioValue = aspectRatioSelect.value;

                    if (aspectRatioValue) {
                        var aspectRatioArray = aspectRatioValue.split(':');
                        aspectRatio = aspectRatioArray[1] / aspectRatioArray[0];
                    }
                }
                
				var rect = {
					x: [
						Math.max(0, Math.min(imageSize.width, startPos.x)),
						Math.max(0, Math.min(imageSize.width, event.clientX - el.getBoundingClientRect().x - imageSize.computedLeft))
					],
					y: [
						Math.max(0, Math.min(imageSize.height, startPos.y)),
						Math.max(0, Math.min(imageSize.height, event.clientY - el.getBoundingClientRect().y - imageSize.computedTop))
					]
				};

                if (null !== aspectRatio) {
                    if (startPos.y < rect.y[1]) {
                        rect.y[1] = rect.y[0] + (Math.abs(rect.x[1] - rect.x[0]) * aspectRatio);
                    } else {
                        rect.y[1] = rect.y[0] - (Math.abs(rect.x[1] - rect.x[0]) * aspectRatio);
                    }

                    if (rect.y[1] >= imageSize.height) {
                        rect.x[1] = Math.max(0, Math.min(imageSize.width, rect.x[0] + ((imageSize.height - rect.y[0]) * (1 / aspectRatio))));
                        rect.y[1] = imageSize.height;
                    }

                    if (rect.y[1] <= 0) {
                        if (startPos.x < rect.x[1]) {
                            rect.x[1] = Math.max(0, Math.min(imageSize.width, rect.x[0] + (rect.y[0] * (1 / aspectRatio))));
                        } else {
                            rect.x[1] = Math.max(0, Math.min(imageSize.width, rect.x[0] - (rect.y[0] * (1 / aspectRatio))));
                        }
                        rect.y[1] = 0;
                    }
                }

                partElement.style.top = Math.min(rect.y[0], rect.y[1]) + imageSize.computedTop + 'px',
                partElement.style.left = Math.min(rect.x[0], rect.x[1]) + imageSize.computedLeft + 'px',
                partElement.style.width = Math.abs(rect.x[0] - rect.x[1]) + 'px',
                partElement.style.height = Math.abs(rect.y[0] - rect.y[1]) + 'px'
				updateValues();
			},
			stop = function(event) {
				move(event);
				isDrawing = false;
			},
			init = function() {
                inputElements = getImportantPartData(getCurrentAlternative());
                el.classList.add('tl_edit_preview_enabled');
                partElement = document.createElement('div');
                partElement.classList.add('tl_edit_preview_important_part');
                el.appendChild(partElement);
				updateImage();
                imageElement.addEventListener('load', updateImage);
                el.addEventListener('mousedown', start);
                el.addEventListener('touchstart', start);
                document.addEventListener('mousemove', move);
                document.addEventListener('touchmove', move);
                document.addEventListener('mouseup', stop);
                document.addEventListener('touchend', stop);
                document.addEventListener('touchcancel', stop);
                document.addEventListener('resize', updateImage);
                document.querySelector('select[name="importantPartSwitch"]').addEventListener('change', () => {
                    inputElements = getImportantPartData(getCurrentAlternative());
                    updateImage();
                });
			}
		;

		init();
	};

    const mainInit = (element) => {
        if (mainInitialized) {
            return;
        }

        mainInitialized = true;

        importantPartsInput = element;
        importantPartsInput.closest('.widget').style.display = 'none';
        editPreviewWizard();
    };

    importantPartsInput = document.getElementById('ctrl_importantParts');

    if (null !== importantPartsInput) {
        mainInit(importantPartsInput);
    }

    new MutationObserver(function (mutationsList) {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (element) {
                    if (element.matches && element.matches('#ctrl_importantParts')) {
                        mainInit(element);
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
