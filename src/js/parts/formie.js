export function formieForm() {
      document.addEventListener('onFormieInit', (event) => {
            // Fetch the Form Factory once it's been loaded
            const formElement = event.detail.form.$form;
            if (formElement) {
                  // Active state on inputs
                  const inputs = formElement.querySelectorAll(".fui-input");
                  if (inputs.length) {
                        inputs.forEach((input) => {
                              var inputContainer = input.closest(".fui-field-container");
                              if (inputContainer) {
                                    if (input.value.length > 0) {
                                          inputContainer.classList.add("is-active");
                                    } else {
                                          inputContainer.classList.remove("is-active");
                                    }

                                    input.addEventListener("focus", function () {
                                          inputContainer.classList.add("is-active");
                                    });

                                    input.addEventListener("focusout", function () {
                                          0 === input.value.length && inputContainer.classList.remove("is-active");
                                    });
                              }
                        });
                  }

                  // Change textarea size on typing
                  const textareas = formElement.querySelectorAll("textarea");
                  if (textareas.length) {
                        textareas.forEach((textarea) => {
                              textarea.addEventListener("keydown", (e) => {
                                    setTimeout(function () {
                                          textarea.style.cssText = "height:auto; padding:0";
                                          textarea.style.cssText = "height:" + textarea.scrollHeight + "px";
                                    }, 0);
                              });
                        });
                  }
            }

            // Refresh the necessary bits that are statically cached (CSRF inputs, captchas, etc)
            const hashId = event.detail.form.config.formHashId;
            if (hashId) {
                  event.detail.formie.refreshForCache(hashId);
            }
      });
}