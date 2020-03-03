function geId(id)
{
    return document.getElementById(id);
}

function td_showOrHideBlock(id)
{
    let box = geId('td-ibx' + id);
    let btn = geId('td-ibtn' + id);

    if (box.className === 'td-bx td-show') {
        box.className = 'td-bx';
        btn.className = 'td-close';
    }
    else {
        box.className = 'td-bx td-show';
        btn.className = 'td-open';
    }
}

function td_showFullString(id)
{
    let block = geId('td-ibs' + id);
    block.innerHTML = '"' + block.dataset.string + '"';
}

