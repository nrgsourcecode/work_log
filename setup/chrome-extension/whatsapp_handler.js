function updateTitle() {
    const span = document.querySelector(
        '#main > header > div:first-child span'
    );

    if (!span) {
        return;
    }

    const name = span.textContent.trim();

    if (!name) {
        return;
    }

    const newTitle = name + ' - WhatsApp';

    if (document.title !== newTitle) {
        console.log('Updating title...' + document.title + ' -> ' + newTitle);
        document.title = newTitle;
    }
}

const observer = new MutationObserver(() => updateTitle());

observer.observe(document.body, {
    childList: true,
    subtree: true,
    characterData: true
});

updateTitle();
