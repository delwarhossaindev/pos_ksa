/** Prints through one hidden iframe, so receipts/slips print without leaving the current screen. */
let frame = null;

function getFrame() {
    if (!frame || !document.body.contains(frame)) {
        frame = document.createElement('iframe');
        frame.title = 'print';
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:absolute;width:0;height:0;border:0;visibility:hidden';
        document.body.append(frame);
    }
    return frame;
}

function printWhenLoaded(f, fallbackUrl) {
    f.onload = () => {
        try {
            f.contentWindow.focus();
            f.contentWindow.print();
        } catch (e) {
            if (fallbackUrl) window.open(fallbackUrl, '_blank');
        }
    };
}

export function printUrl(url) {
    const f = getFrame();
    f.removeAttribute('srcdoc');
    printWhenLoaded(f, url);
    f.src = url;
}

export function printHtml(html) {
    const f = getFrame();
    printWhenLoaded(f, null);
    f.srcdoc = html;
}
