// 1. Setup Drag & Drop, Clipboard Paste, and URL Previews for Cover & Album images
function setupImageZone(dropZoneId, fileInputId, urlInputId, previewId) {
  const dropZone = document.getElementById(dropZoneId);
  const fileInput = document.getElementById(fileInputId);
  const urlInput = document.getElementById(urlInputId);
  const preview = document.getElementById(previewId);

  const showPreview = (src) => {
    preview.src = src;
    preview.hidden = false;
  };

  preview.addEventListener("error", () => {
    preview.hidden = true;
  });

  const assignFile = (file) => {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    urlInput.value = "";
    showPreview(URL.createObjectURL(file));
  };

  dropZone.addEventListener("click", () => fileInput.click());

  dropZone.addEventListener("dragover", (e) => {
    e.preventDefault();
    dropZone.classList.add("dragover");
  });

  dropZone.addEventListener("dragleave", () => {
    dropZone.classList.remove("dragover");
  });

  dropZone.addEventListener("drop", (e) => {
    e.preventDefault();
    dropZone.classList.remove("dragover");

    // Case A: Dropped a file from file manager
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith("image/")) {
      assignFile(file);
      return;
    }

    // Case B: Dragged an image directly from another browser tab
    const droppedUrl = e.dataTransfer.getData("text/uri-list") || e.dataTransfer.getData("text/plain");
    if (droppedUrl && droppedUrl.startsWith("http")) {
      fileInput.value = "";
      urlInput.value = droppedUrl.trim();
      showPreview(droppedUrl.trim());
    }
  });

  // Allow Ctrl+V pasting a copied image directly into the URL input box
  urlInput.addEventListener("paste", (e) => {
    if (e.clipboardData && e.clipboardData.files.length > 0) {
      const file = e.clipboardData.files[0];
      if (file.type.startsWith("image/")) {
        e.preventDefault();
        assignFile(file);
      }
    }
  });

  fileInput.addEventListener("change", () => {
    const file = fileInput.files[0];
    if (file) {
      urlInput.value = "";
      showPreview(URL.createObjectURL(file));
    }
  });

  urlInput.addEventListener("input", () => {
    const url = urlInput.value.trim();
    if (url.startsWith("http")) {
      fileInput.value = "";
      showPreview(url);
    } else if (!fileInput.files.length) {
      preview.hidden = true;
    }
  });
}

setupImageZone("cover-drop", "cover-file", "cover-url", "cover-preview");
setupImageZone("album-drop", "album-file", "album-url", "album-preview");

// 2. Display selected local audio filename
const audioInput = document.getElementById("audio-file");
const audioFilename = document.getElementById("audio-filename");

audioInput.addEventListener("change", () => {
  audioFilename.textContent = audioInput.files[0]
    ? audioInput.files[0].name
    : "No file selected";
});

// Helper: Try fetching image URL in browser first (shares browser session/cache if CORS permits)
async function tryFetchImageInBrowser(url, filename) {
  if (!url) return null;
  try {
    const res = await fetch(url, { mode: "cors", credentials: "omit" });
    if (res.ok) {
      const blob = await res.blob();
      if (blob.type.startsWith("image/") && blob.size > 100) {
        return new File([blob], filename, { type: blob.type });
      }
    }
  } catch (_) {
    // CORS restricted; convert.php will download it server-side
  }
  return null;
}

// 3. Send data to convert.php and download the converted audio file
const form = document.getElementById("music-form");
const processBtn = document.getElementById("process-btn");

form.addEventListener("submit", async (e) => {
  e.preventDefault();

  const link = document.getElementById("music-link").value.trim();
  const coverUrl = document.getElementById("cover-url").value.trim();
  const albumUrl = document.getElementById("album-url").value.trim();
  let coverFile = document.getElementById("cover-file").files[0];
  let albumFile = document.getElementById("album-file").files[0];
  const audioFile = document.getElementById("audio-file").files[0];
  const format = document.querySelector('input[name="format"]:checked').value;

  if (!link && !audioFile) {
    alert("Please enter a link or select a local audio file first.");
    return;
  }

  const originalText = processBtn.textContent;
  processBtn.disabled = true;
  processBtn.textContent = `Converting to .${format.toUpperCase()}...`;

  try {
    // If only URL was given, try grabbing the image blob in the browser first
    if (!coverFile && coverUrl) {
      coverFile = await tryFetchImageInBrowser(coverUrl, "cover_browser_img");
    }
    if (!albumFile && albumUrl) {
      albumFile = await tryFetchImageInBrowser(albumUrl, "album_browser_img");
    }

    const formData = new FormData();
    formData.append("link", link);
    formData.append("audio_format", format);
    formData.append("cover_url", coverUrl);
    formData.append("album_url", albumUrl);
    if (coverFile) formData.append("cover_file", coverFile);
    if (albumFile) formData.append("album_file", albumFile);
    if (audioFile) formData.append("audio_file", audioFile);

    const response = await fetch("convert.php", {
      method: "POST",
      body: formData,
    });

    if (!response.ok) {
      let errMsg = "Conversion failed";
      try {
        const err = await response.json();
        if (err.detail) errMsg = err.detail;
      } catch (_) {}
      throw new Error(errMsg);
    }

    const disposition = response.headers.get("Content-Disposition");
    let filename = `audio.${format}`;

    if (disposition) {
      const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
      if (utf8Match && utf8Match[1]) {
        filename = decodeURIComponent(utf8Match[1]);
      } else {
        const standardMatch = disposition.match(/filename="([^"]+)"/i);
        if (standardMatch && standardMatch[1]) {
          filename = standardMatch[1];
        }
      }
    }

    const blob = await response.blob();
    const downloadUrl = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = downloadUrl;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(downloadUrl);

    processBtn.textContent = "Downloaded!";
    setTimeout(() => {
      processBtn.textContent = originalText;
    }, 2500);
  } catch (error) {
    alert(`Error: ${error.message}`);
    processBtn.textContent = originalText;
  } finally {
    processBtn.disabled = false;
  }
});