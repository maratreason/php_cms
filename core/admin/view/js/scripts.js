document.querySelector(".sitemap-button").onclick = (e) => {
  e.preventDefault();

  createSitemap();
};

let links_counter = 0;

// Парсинг сайтов
function createSitemap() {
  links_counter++;

  Ajax({data: {
    ajax: 'sitemap',
    links_counter: links_counter
  }})
    .then((response) => {
      console.log("Success - " + response);
    })
    .catch((err) => {
      console.log("Error - " + err);
      createSitemap();
    });
}

createFile();

function createFile() {
  let files = document.querySelectorAll('input[type=file]');
  let fileStore = [];

  if (files.length) {
    files.forEach(item => {
      item.onchange = function() {
        let multiple = false;
        let parentContainer;
        let container;

        if (item.hasAttribute("multiple")) {
          multiple = true;
          parentContainer = this.closest(".gallery_container");

          if (!parentContainer) return false;

          container = parentContainer.querySelectorAll(".empty_container");

          if (container.length < this.files.length) {
            for (let index = 0; index < this.files.length - container.length; index++) {
              let el = document.createElement("div");
              el.classList.add("vg-dotted-square", "vg-center", "empty_container");
              parentContainer.append(el);
            }

            container = parentContainer.querySelectorAll(".empty_container");
          }
        }

        let fileName = item.name;
        let attributeName = fileName.replace(/[\[\]]/g, "");

        for (let i in item.files) {
          if (item.files.hasOwnProperty(i)) {

            if (multiple) {
              if (typeof fileStore[fileName] === "undefined") fileStore[fileName] = [];

              let elId = fileStore[fileName].push(item.files[i]) - 1;
              container[i].setAttribute(`data-deleteFileId-${attributeName}`, elId);

              showImage(item.files[i], container[i],
              //   function () {
              //   parentContainer.sortable({
              //     excludedElements: "label .empty_container",
              //   });
              // }
              );

              deleteNewFiles(elId, fileName, attributeName, container[i]);
            } else {
              container = item.closest(".img_container").querySelector(".img_show");
              showImage(item.files[i], container);
            }
          }
        }

        console.log(fileStore);
      }

      let area = item.closest(".img_wrapper");

      if (area) {
        dragAndDrop(area, item);
      }

    });
  }

  let form = document.querySelector("#main-form");

  if (form) {
    form.onsubmit = function (e) {
      // createJsSortable(form);

      if (!isEmpty(fileStore)) {
        e.preventDefault();

        let formData = new FormData(this);

        for (let i in fileStore) {
          if (fileStore.hasOwnProperty(i)) {
            formData.delete(i);

            let rowName = i.replace(/[\[\]]/g, "");

            fileStore[i].forEach((item, index) => {
              formData.append(`${rowName}[${index}]`, item);
            });
          }
        }

        formData.append("ajax", "editData");

        Ajax({
          url: this.getAttribute('action'),
          type: 'post',
          data: formData,
          processData: false,
          contentType: false,
        }).then((res) => {
          try {
            res = JSON.parse(res);

            if (!res.success) throw new Error();

            location.reload();
          } catch (err) {
            alert("Произошла внутренняя ошибка", err);
          }
        });
      }
    };
  }

  // Удаление файлов
  function deleteNewFiles(elId, fileName, attributeName, container) {
    container.addEventListener("click", function () {
      if(e.target === container){
        this.remove();
        // Удаляем из массива, но с сохранением ключей, элемент станет empty, но длина массива останется такой же.
        delete fileStore[fileName][elId];
      }
    });
  }

  // Показ картинок при добавлении
  function showImage(item, container, callback) {
    let reader = new FileReader();
    container.innerHTML = "";
    reader.readAsDataURL(item);

    reader.onload = (e) => {
      container.innerHTML = '<img class="img_item" src="">';
      container.querySelector("img").setAttribute("src", e.target.result);
      container.classList.remove("empty_container");
      // callback && callback();
    };
  }

  function dragAndDrop(area, input) {
    // пошел, двигается, вышел, упал
    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName, index) => {
      area.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (index < 2) {
          area.style.background = "lightblue";
        } else {
          area.style.background = "#fff";

          // если drop
          if (index === 3) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event("change"));
          }
        }
      });
    });
  }

}

function isEmpty(arr) {
  for (let i in arr) {
    return false;
  }

  return true;
}

changeMenuPosition();

function changeMenuPosition() {
  let form = document.querySelector("#main-form");

  if (form) {
    let selectParent = form.querySelector("select[name=parent_id]");
    let selectPosition = form.querySelector("select[name=menu_position]");

    if (selectPosition && selectParent) {
      let defaultParent = selectParent.value;
      let defaultPosition = +selectPosition.value;

      selectParent.addEventListener("change", function () {
        let defaultChoose = false;

        if (this.value === defaultParent) defaultChoose = true;

        Ajax({
          data: {
            table: form.querySelector("input[name=table]").value,
            parent_id: this.value,
            ajax: "change_parent",
            iteration: !form.querySelector("#tableId") ? 1 : +!defaultChoose,
          },
        }).then((res) => {
          res = +res;

          if (!res) return errorAlert();

          let newSelect = document.createElement("select");
          newSelect.setAttribute("name", "menu_position");
          newSelect.classList.add("vg-input", "vg-text", "vg-full", "vg-firm-color1");

          for (let i = 1; i <= res; i++) {
            let selected = defaultChoose && i === defaultPosition ? "selected" : "";

            newSelect.insertAdjacentHTML("beforeend", `<option ${selected} value="${i}">${i}</option>`);
          }

          selectPosition.before(newSelect);
          selectPosition.remove();

          selectPosition = newSelect;
        });
      });
    }
  }
}

blockParameters();

// Аккордеон
function blockParameters() {
  let wraps = document.querySelectorAll(".select_wrap");

  if (wraps.length) {
    let selectAllIndexes = [];

    wraps.forEach((item) => {
      let next = item.nextElementSibling;

      if (next && next.classList.contains("option_wrap")) {
        item.addEventListener("click", (e) => {
          if (!e.target.classList.contains("select_all")) {
            // аккордеон
            // Element.prototype.slideToggle
            next.slideToggle();
          } else {
            // Выделение всех чекбоксов
            let index = [...document.querySelectorAll(".select_all")].indexOf(e.target);

            if (typeof selectAllIndexes[index] === "undefined") selectAllIndexes[index] = false;

            selectAllIndexes[index] = !selectAllIndexes[index];

            next.querySelectorAll("input[type=checkbox]").forEach((el) => (el.checked = selectAllIndexes[index]));
          }
        });
      }
    });
  }
}

showHideMenuSearch();

// Скрипт показа и скрытия меню и поиска
function showHideMenuSearch() {
  document.querySelector("#hideButton").addEventListener("click", () => {
    document.querySelector(".vg-carcass").classList.toggle("vg-hide");
  });

  const searchBtn = document.querySelector("#searchButton");
  const searchInput = searchBtn.querySelector("input[type=text]");

  searchBtn.addEventListener("click", () => {
    searchBtn.classList.add("vg-search-reverse");
    searchInput.focus();
  });

  searchInput.addEventListener("blur", () => {
    searchBtn.classList.remove("vg-search-reverse");
  });
}
