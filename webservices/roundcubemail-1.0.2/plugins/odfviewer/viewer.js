/**
 * @license
 * Copyright (C) 2013 KO GmbH <copyright@kogmbh.com>
 *
 * @licstart
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Affero General Public License
 * (GNU AGPL) as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.  The code is distributed
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU AGPL for more details.
 *
 * As additional permission under GNU AGPL version 3 section 7, you
 * may distribute non-source (e.g., minimized or compacted) forms of
 * that code without the copy of the GNU GPL normally required by
 * section 4, provided you include this license notice and a URL
 * through which recipients can access the Corresponding Source.
 *
 * As a special exception to the AGPL, any HTML file which merely makes function
 * calls to this code, and for that purpose includes it by reference shall be
 * deemed a separate work for copyright law purposes. In addition, the copyright
 * holders of this code give you permission to combine this code with free
 * software libraries that are released under the GNU LGPL. You may copy and
 * distribute such a system following the terms of the GNU AGPL for this code
 * and the LGPL for the libraries. If you modify this code, you may extend this
 * exception to your version of the code, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your
 * version.
 *
 * This license applies to this entire compilation.
 * @licend
 * @source: http://www.webodf.org/
 * @source: http://gitorious.org/webodf/webodf/
 */

/*global document, window*/

function Viewer(viewerPlugin, docurl) {
    "use strict";

    var self = this,
        kScrollbarPadding = 40,
        kMinScale = 0.25,
        kMaxScale = 4.0,
        kDefaultScaleDelta = 1.1,
        kDefaultScale = 'auto',
        presentationMode = false,
        initialized = false,
        isSlideshow = false,
        url,
        viewerElement,
        canvasContainer = document.getElementById('canvasContainer'),
        overlayNavigator = document.getElementById('overlayNavigator'),
        pageSwitcher = document.getElementById('toolbarLeft'),
        zoomWidget = document.getElementById('toolbarMiddleContainer'),
        scaleSelector = document.getElementById('scaleSelect'),
        filename,
        pages = [],
        currentPage,
        scaleChangeTimer,
        touchTimer;

    function isFullScreen() {
    // Note that the browser fullscreen (triggered by short keys) might
    // be considered different from content fullscreen when expecting a boolean
        return document.isFullScreen || document.mozFullScreen || document.webkitIsFullScreen;
    }

    function selectScaleOption(value) {
        // Retrieve the options from the zoom level <select> element
        var options = scaleSelector.options,
            option,
            predefinedValueFound = false,
            i;

        for (i = 0; i < options.length; i += 1) {
            option = options[i];
            if (option.value !== value) {
                option.selected = false;
                continue;
            }
            option.selected = true;
            predefinedValueFound = true;
        }
        return predefinedValueFound;
    }

    function getPages() {
        return viewerPlugin.getPages();
    }

    function setScale(val, resetAutoSettings, noScroll) {
        if (val === self.getZoomLevel()) {
            return;
        }

        self.setZoomLevel(val);

        var event = document.createEvent('UIEvents');
        event.initUIEvent('scalechange', false, false, window, 0);
        event.scale = val;
        event.resetAutoSettings = resetAutoSettings;
        window.dispatchEvent(event);
    }

    function onScroll() {
        var pageNumber;

        if (viewerPlugin.onScroll) {
            viewerPlugin.onScroll();
        }
        if (viewerPlugin.getPageInView) {
            pageNumber = viewerPlugin.getPageInView();
            if (pageNumber) {
                currentPage = pageNumber;
                document.getElementById('pageNumber').value = pageNumber;
            }
        }
    }

    function delayedRefresh(milliseconds) {
        window.clearTimeout(scaleChangeTimer);
        scaleChangeTimer = window.setTimeout(function () {
            onScroll();
        }, milliseconds);
    }

    function parseScale(value, resetAutoSettings, noScroll) {
        var scale,
            maxWidth,
            maxHeight;

        if (value === 'custom') {
            scale = parseFloat(document.getElementById('customScaleOption').textContent) / 100;
        } else {
            scale = parseFloat(value);
        }

        if (scale) {
            setScale(scale, true, noScroll);
            delayedRefresh(300);
            return;
        }

        maxWidth = canvasContainer.clientWidth - kScrollbarPadding;
        maxHeight = canvasContainer.clientHeight - kScrollbarPadding;

        switch (value) {
        case 'page-actual':
            setScale(1, resetAutoSettings, noScroll);
            break;
        case 'page-width':
            viewerPlugin.fitToWidth(maxWidth);
            break;
        case 'page-height':
            viewerPlugin.fitToHeight(maxHeight);
            break;
        case 'page-fit':
            viewerPlugin.fitToPage(maxWidth, maxHeight);
            break;
        case 'auto':
            if (viewerPlugin.isSlideshow()) {
                viewerPlugin.fitToPage(maxWidth + kScrollbarPadding, maxHeight + kScrollbarPadding);
            } else {
                viewerPlugin.fitSmart(maxWidth);
            }
            break;
        }

        selectScaleOption(value);
        delayedRefresh(300);
    }

    
    this.initialize = function (url) {
        viewerElement = document.getElementById('viewer');
        filename = url.replace(/^.*[\\\/]/, '');
        document.title = filename;
        document.getElementById('documentName').innerHTML = document.title;

        viewerPlugin.onLoad = function () {
            isSlideshow = viewerPlugin.isSlideshow();
            if (isSlideshow) {
                // No padding for slideshows
                canvasContainer.style.padding = 0;
                // Show page nav controls only for presentations
                pageSwitcher.style.visibility = 'visible';
            } else {
                // For text documents, show the zoom widget.
                zoomWidget.style.visibility = 'visible';
                // Only show the page switcher widget if the plugin supports page numbers
                if (viewerPlugin.getPageInView) {
                    pageSwitcher.style.visibility = 'visible';
                }
            }

            initialized = true;
            pages = getPages();
            document.getElementById('numPages').innerHTML = 'of ' + pages.length;

            self.showPage(1);

            // Set default scale
            parseScale(kDefaultScale);

            canvasContainer.onscroll = onScroll;
            delayedRefresh();
        };

        viewerPlugin.initialize(canvasContainer, url);
    };

    /**
     * Shows the 'n'th page. If n is larger than the page count,
     * shows the last page. If n is less than 1, shows the first page.
     * @return {undefined}
     */
    this.showPage = function (n) {
        if (n <= 0) {
            n = 1;
        } else if (n > pages.length) {
            n = pages.length;
        }

        viewerPlugin.showPage(n);

        currentPage = n;
        document.getElementById('pageNumber').value = currentPage;
    };

    /**
     * Shows the next page. If there is no subsequent page, does nothing.
     * @return {undefined}
     */
    this.showNextPage = function () {
        self.showPage(currentPage + 1);
    };

    /**
     * Shows the previous page. If there is no previous page, does nothing.
     * @return {undefined}
     */
    this.showPreviousPage = function () {
        self.showPage(currentPage - 1);
    };

    /**
     * Attempts to 'download' the file.
     * @return {undefined}
     */
    this.download = function () {
        var documentUrl = url.split('#')[0];
        documentUrl += '#viewer.action=download';
        window.open(documentUrl, '_parent');
    };

    /**
     * Toggles the fullscreen state of the viewer
     * @return {undefined}
     */
    this.toggleFullScreen = function () {
        var elem = viewerElement;
        if (!isFullScreen()) {
            if (elem.requestFullScreen) {
                elem.requestFullScreen();
            } else if (elem.mozRequestFullScreen) {
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullScreen) {
                elem.webkitRequestFullScreen();
            }
        } else {
            if (document.cancelFullScreen) {
                document.cancelFullScreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitCancelFullScreen) {
                document.webkitCancelFullScreen();
            }
        }
    };
 
    /**
     * Toggles the presentation mode of the viewer.
     * Presentation mode involves fullscreen + hidden UI controls
     */
    this.togglePresentationMode = function () {
        var titlebar = document.getElementById('titlebar'),
            toolbar = document.getElementById('toolbarContainer'),
            overlayCloseButton = document.getElementById('overlayCloseButton');

        if (!presentationMode) {
            titlebar.style.display = toolbar.style.display = 'none';
            overlayCloseButton.style.display = 'block';
            canvasContainer.className = 'presentationMode';
            isSlideshow = true;
            canvasContainer.onmousedown = function (event) {
                event.preventDefault();
            };
            canvasContainer.oncontextmenu = function (event) {
                event.preventDefault();
            };
            canvasContainer.onmouseup = function (event) {
                event.preventDefault();
                if (event.which === 1) {
                    self.showNextPage();
                } else {
                    self.showPreviousPage();
                }
            };
            parseScale('page-fit');
        } else {
            titlebar.style.display = toolbar.style.display = 'block';
            overlayCloseButton.style.display = 'none';
            canvasContainer.className = '';
            canvasContainer.onmouseup = function () {};
            canvasContainer.oncontextmenu = function () {};
            canvasContainer.onmousedown = function () {};
            parseScale('auto');
            isSlideshow = viewerPlugin.isSlideshow();
        }

        presentationMode = !presentationMode;
    };

    /**
     * Gets the zoom level of the document
     * @return {!number}
     */
    this.getZoomLevel = function () {
        return viewerPlugin.getZoomLevel();
    };

    /**
     * Set the zoom level of the document
     * @param {!number} value
     * @return {undefined}
     */
    this.setZoomLevel = function (value) {
        viewerPlugin.setZoomLevel(value);
    };

    /**
     * Zoom out by 10 %
     * @return {undefined}
     */
    this.zoomOut = function () {
        // 10 % decrement
        var newScale = (self.getZoomLevel() / kDefaultScaleDelta).toFixed(2);
        newScale = Math.max(kMinScale, newScale);
        parseScale(newScale, true);
    };

    /**
     * Zoom in by 10%
     * @return {undefined}
     */
    this.zoomIn = function () {
        // 10 % increment
        var newScale = (self.getZoomLevel() * kDefaultScaleDelta).toFixed(2);
        newScale = Math.min(kMaxScale, newScale);
        parseScale(newScale, true);
    };

    function cancelPresentationMode() {
        if (presentationMode && !isFullScreen()) {
            self.togglePresentationMode();
        }
    }

    function showOverlayNavigator() {
        if (isSlideshow) {
            overlayNavigator.className = 'touched';
            window.clearTimeout(touchTimer);
            touchTimer = window.setTimeout(function () {
                overlayNavigator.className = '';
            }, 2000);
        }
    }

    function init(docurl) {

        self.initialize(docurl);

        if (!(document.cancelFullScreen || document.mozCancelFullScreen || document.webkitCancelFullScreen)) {
            document.getElementById('fullscreen').style.visibility = 'hidden';
        }

        document.getElementById('overlayCloseButton').addEventListener('click', self.toggleFullScreen);
        document.getElementById('fullscreen').addEventListener('click', self.toggleFullScreen);
        document.getElementById('presentation').addEventListener('click', function () {
            if (!isFullScreen()) {
                self.toggleFullScreen();
            }
            self.togglePresentationMode();
        });

        document.addEventListener('fullscreenchange', cancelPresentationMode);
        document.addEventListener('webkitfullscreenchange', cancelPresentationMode);
        document.addEventListener('mozfullscreenchange', cancelPresentationMode);

        document.getElementById('download').addEventListener('click', function () {
            self.download();
        });

        document.getElementById('zoomOut').addEventListener('click', function () {
            self.zoomOut();
        });

        document.getElementById('zoomIn').addEventListener('click', function () {
            self.zoomIn();
        });

        document.getElementById('previous').addEventListener('click', function () {
            self.showPreviousPage();
        });

        document.getElementById('next').addEventListener('click', function () {
            self.showNextPage();
        });

        document.getElementById('previousPage').addEventListener('click', function () {
            self.showPreviousPage();
        });

        document.getElementById('nextPage').addEventListener('click', function () {
            self.showNextPage();
        });

        document.getElementById('pageNumber').addEventListener('change', function () {
            self.showPage(this.value);
        });

        document.getElementById('scaleSelect').addEventListener('change', function () {
            parseScale(this.value);
        });

        canvasContainer.addEventListener('click', showOverlayNavigator);
        overlayNavigator.addEventListener('click', showOverlayNavigator);

        window.addEventListener('scalechange', function (evt) {
            var customScaleOption = document.getElementById('customScaleOption'),
                predefinedValueFound = selectScaleOption(String(evt.scale));

            customScaleOption.selected = false;

            if (!predefinedValueFound) {
                customScaleOption.textContent = Math.round(evt.scale * 10000) / 100 + '%';
                customScaleOption.selected = true;
            }
        }, true);

        window.addEventListener('resize', function (evt) {
            if (initialized &&
                      (document.getElementById('pageWidthOption').selected ||
                      document.getElementById('pageAutoOption').selected)) {
                parseScale(document.getElementById('scaleSelect').value);
            }
            showOverlayNavigator();
        });

        window.addEventListener('keydown', function (evt) {
            var key = evt.keyCode,
                shiftKey = evt.shiftKey;

            switch (key) {
            case 33: // pageUp
            case 38: // up
            case 37: // left
                self.showPreviousPage();
                break;
            case 34: // pageDown
            case 40: // down
            case 39: // right
                self.showNextPage();
                break;
            case 32: // space
                shiftKey ? self.showPreviousPage() : self.showNextPage();
                break;
            }
        });
    }

    init(docurl);
}
