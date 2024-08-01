document.querySelector(".sitemap-button").onclick = (e) => {
  e.preventDefault();
  
  Ajax({
    type: "POST",
  })
    .then((response) => {
      console.log("Success - " + response);
    })
    .catch((err) => {
      console.log("Error - " + err);
    });
}
