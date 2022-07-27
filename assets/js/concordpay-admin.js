const cpbSectionBtnSettings = document.querySelector('#cpb_section_btn_settings');
const cpbButtonPreview = document.querySelector('#btn_preview');
const cpbButtonSettings = ['btn_shape', 'btn_height', 'btn_width', 'btn_color', 'btn_border', 'btn_inverse'];
if (typeof cpbSectionBtnSettings != 'undefined' && cpbSectionBtnSettings) {
  cpbSectionBtnSettings.addEventListener('change', function(e) {
    let el = e.target;
    if (cpbButtonSettings.includes(el.id)) {
      let newValue = el.value;
      let newClass = '';
      switch (el.id) {
        case 'btn_shape':
          newClass = `cpb-btn-shape-${newValue}`;
          cpbButtonPreview.className = cpbButtonPreview.className.replace(/cpb-btn-shape[^\s]+/g, newClass);
          break;
        case 'btn_height':
          newClass = `cpb-btn-height-${newValue}`;
          cpbButtonPreview.className = cpbButtonPreview.className.replace(/cpb-btn-height[^\s]+/g, newClass);
          break;
        case 'btn_width':
          cpbButtonPreview.style.width = `${newValue}px`;
          break;
        case 'btn_color':
          let color = '#FFFFFF';
          if (el.value === 'gold') {
            color = '#FFC439';
          } else if (el.value === 'blue') {
            color = '#0170BA';
          } else if (el.value === 'silver') {
            color = '#EEEEEE';
          } else if (el.value === 'white') {
            color = '#FFFFFF';
          } else if (el.value === 'black') {
            color = '#2C2E2F';
          }
          cpbButtonPreview.style.backgroundColor = color;
          break;
        case 'btn_border':
          newClass = `cpb-btn-border-${newValue}`;
          cpbButtonPreview.className = cpbButtonPreview.className.replace(/cpb-btn-border[^\s]+/g, newClass);
          break;
        case 'btn_inverse':
          toggleBtnImage(el.value, cpbButtonPreview);
          break;
      }
    }
  });
}

function toggleBtnImage(type, btn) {
  let isNormal = btn.style.backgroundImage.includes('concordpay.svg');
  if (isNormal) {
    btn.style.backgroundImage = btn.style.backgroundImage.replace('concordpay.svg', 'concordpay-inverse.svg');
  } else {
    btn.style.backgroundImage = btn.style.backgroundImage.replace('concordpay-inverse.svg', 'concordpay.svg');
  }
}