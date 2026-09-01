document.addEventListener("DOMContentLoaded", () => {
    console.log("Wgrano skrypt!");
    // Use event delegation to handle clicks on all current and future buttons.
    document.body.addEventListener("click", function (event) {
        if (event.target.matches(".load-comments-btn")) {
            const button = event.target;
            const postId = button.dataset.postId;
            const commentsContainer = document.getElementById(`comments-for-${postId}`);

            if (!postId || !commentsContainer) return;

            // Change the button text and disable it to avoid repeated clicks.
            button.textContent = "Ładowanie...";
            button.setAttribute("aria-busy", "true");
            button.disabled = true;

            // Approach 2: load data on the client side with JavaScript.
            fetch(`/api/v1/posts/${postId}/comments`)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Błąd sieci: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then((comments) => {
                    // Hide the button after comments are loaded successfully.
                    button.style.display = "none";

                    if (comments.error) {
                        commentsContainer.innerHTML = `<p class="error-text">${comments.error}</p>`;
                        return;
                    }

                    if (comments.length > 0) {
                        let html = "<h4>Komentarze:</h4><ul>";
                        comments.forEach((comment) => {
                            // Basic sanitization to avoid XSS.
                            const author = comment.author.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                            const content = comment.content.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                            html += `<li><strong>${author}:</strong> ${content}</li>`;
                        });
                        html += "</ul>";
                        commentsContainer.innerHTML = html;
                    } else {
                        commentsContainer.innerHTML = "<p>Brak komentarzy.</p>";
                    }
                })
                .catch((error) => {
                    console.error("Błąd podczas pobierania komentarzy:", error);
                    commentsContainer.innerHTML = `<p class="error-text">Nie udało się załadować komentarzy. Spróbuj ponownie później.</p>`;
                    // Re-enable the button so the user can try again.
                    button.textContent = "Spróbuj ponownie";
                    button.disabled = false;
                });
        }
    });
});
