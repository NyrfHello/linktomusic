# 🎵 linktomusic

## 📌 Table of Contents

* [Overview](#-overview)

* [The Problem vs. The Solution](#-the-problem-vs-the-solution)

* [Terminal Preview](#-terminal-preview)

* [Key Features](#-key-features)

* [Installation](#-installation)

* [Quick Start & Common Workflows](#-quick-start--common-workflows)

* [Command-Line Reference](#-command-line-reference)

* [Configuration](#-configuration)

* [How It Works (Under the Hood)](#-how-it-works-under-the-hood)

* [Roadmap](#-roadmap)

* [Contributing](#-contributing)

* [License & Acknowledgments](#-license--acknowledgments)

## 💡 Overview

When downloading audio from online video and music links using standard command-line tools, you are typically left with messy filenames, stripped ID3 tags, missing cover art, and cumbersome post-processing steps.

**`linktomusic`** is an ultra-fast, zero-hassle terminal utility designed to solve this once and for all. It takes any supported streaming URL and converts it directly into your preferred audio format (**FLAC**, **WAV**, **M4A**, or **MP3**), automatically injecting custom titles, artist tags, album names, and high-resolution cover art in a single command.

## ⚖️ The Problem vs. The Solution

| **What Gets Handled** | **Standard Download Tools (yt-dlp, etc.)** | **Manual Post-Processing** | **linktomusic 🚀** | 
| **Audio Format Conversion** | ⚠️ Manual flags needed | ⚠️ Separate ffmpeg commands | ✅ Integrated selection (`flac`, `wav`, `m4a`, `mp3`) | 
| **Custom Filename Templates** | ⚠️ Complex format strings | ⚠️ Manual renaming | ✅ Automated & Clean | 
| **Embedded Track Metadata** | ❌ Often stripped or generic | ⚠️ Scripting required | ✅ Auto-injected & Custom tags | 
| **Embedded Album Cover Art** | ⚠️ Requires separate flags | ⚠️ Manual embedding tools | ✅ Auto-embedded from source/file | 
| **Uncompressed PCM WAV Support** | ⚠️ Generic output | ⚠️ Manual conversion | ✅ Direct high-res WAV export | 

## 🖥️ Terminal Preview

### 1. Converting a Link to FLAC with Custom Metadata & Cover Art

```
$ linktomusic "https://example.com/track/12345" --format flac --title "Cyberpunk Odyssey" --album "Neon Nights" --cover "artwork.jpg"

[LINKTOMUSIC v1.2.0]
-------------------------------------------------------------------------
Target: https://example.com/track/12345 (Format: FLAC - Lossless)
-------------------------------------------------------------------------

[*] Phase 1: Resolving Source Stream...
    -> Fetching media metadata ... [DONE]
    -> Selecting highest quality audio stream (1411 kbps) ... [DONE]

[*] Phase 2: Transcoding Audio...
    -> Converting stream to FLAC ... [DONE]

[*] Phase 3: Injecting Metadata & Artwork...
    -> Setting Title: "Cyberpunk Odyssey"
    -> Setting Album: "Neon Nights"
    -> Embedding cover art from artwork.jpg ... [DONE]

[★] Complete! Saved to ./Cyberpunk_Odyssey.flac (34.2 MB)

```

### 2. Exporting to Uncompressed WAV

```
$ linktomusic "https://example.com/track/67890" --format wav --name "master_mix"

[LINKTOMUSIC v1.2.0]
-------------------------------------------------------------------------
Target: https://example.com/track/67890 (Format: WAV - Uncompressed PCM)
-------------------------------------------------------------------------

[*] Phase 1: Fetching & Downloading media stream ... [DONE]
[*] Phase 2: Decoding to Uncompressed PCM WAV (16-bit / 44.1kHz) ... [DONE]
[*] Phase 3: Applying custom filename: master_mix.wav ... [DONE]

[★] Complete! Saved to ./master_mix.wav (52.8 MB)

```

## ✨ Key Features

* **🎛️ Multi-Format Powerhouse:** Seamlessly convert any media link into **FLAC** (lossless), **WAV** (uncompressed PCM studio master), **M4A** (AAC high efficiency), or **MP3** (universal compatibility).

* **🏷️ Advanced Metadata Tagging:** Instantly inject or override ID3 and Vorbis tags including track title, artist name, album, track number, and release year.

* **🎨 Embedded Artwork Support:** Automatically attach or custom-supply album cover images (`.jpg`, `.png`) directly into the final audio file container.

* **✏️ Smart Custom Filenames:** Define clean output naming templates or custom file basenames without manual post-renaming.

* **⚡ Blazing Fast & Lightweight:** Zero heavy GUI dependencies. Starts instantly, runs smoothly in scripts, and optimizes CPU/network usage.

## 📥 Installation

### Method 1: Automated Script (Recommended)

```
curl -fsSL https://raw.githubusercontent.com/your-username/linktomusic/main/install.sh | bash

```

### Method 2: Build from Source

```
git clone https://github.com/your-username/linktomusic.git
cd linktomusic
make build
sudo make install

```

## 🚀 Quick Start & Common Workflows

### 1. Convert to FLAC with Custom Metadata

Download and transcode a URL into a high-fidelity FLAC file with custom title and album tags:

```
linktomusic "<URL>" --format flac --title "My Track" --album "Greatest Hits"

```

### 2. Export to Uncompressed WAV

Extract and save a stream into a pristine uncompressed `.wav` file:

```
linktomusic "<URL>" --format wav --name "raw_audio_rip"

```

### 3. Convert to MP3 with Custom Cover Art

Package a downloaded track into an MP3 with an embedded local cover image:

```
linktomusic "<URL>" --format mp3 --cover "cover.jpg"

```

### 4. Quick Download to M4A

Quickly grab a lightweight, high-compatibility M4A file:

```
linktomusic "<URL>" --format m4a

```

## 📖 Command-Line Reference

```
Usage: linktomusic <URL> [OPTIONS]

Arguments:
  <URL>                   The media or track URL to download and convert

Options:
  -f, --format <fmt>      Output audio format: 'flac', 'wav', 'm4a', or 'mp3' [default: mp3]
  -n, --name <filename>   Custom output filename (without extension)
  -t, --title <title>     Set custom track title metadata tag
  -a, --album <album>     Set custom album name metadata tag
  -r, --artist <artist>   Set custom artist name metadata tag
  -c, --cover <image>     Path to image file (.jpg/.png) for embedded artwork
  -v, --verbose           Enable verbose output logs
  -h, --help              Show help and exit

```

## ⚙️ Configuration

`linktomusic` works effortlessly out of the box, but you can configure default formats, default output paths, and tagging rules in `~/.config/linktomusic/config.toml`:

```
[general]
default_format = "flac"      # Options: flac, wav, m4a, mp3
output_dir = "~/Music/Downloads"
embed_cover = true

[naming]
template = "{artist} - {title}"

```

## 🔍 How It Works (Under the Hood)

```
flowchart TD
    A["User triggers: linktomusic &lt;URL&gt;"] --> B["Resolve Stream Source & Metadata"]
    B --> C["Download Raw Audio Stream"]
    C --> D{"Selected Format?"}
    D -- FLAC --> E["Transcode to Lossless FLAC"]
    D -- WAV --> F["Transcode to Uncompressed PCM WAV"]
    D -- M4A --> G["Transcode to AAC M4A"]
    D -- MP3 --> H["Transcode to Compressed MP3"]
    E & F & G & H --> I["Inject Custom ID3/Vorbis Tags"]
    I --> J["Embed Album Artwork (if provided)"]
    J --> K["Save to Output Destination ★"]

```

1. **URL Resolution:** Validates the link and extracts the direct source media stream.

2. **Stream Retrieval:** Downloads the highest available quality audio stream.

3. **Format Transcoding:** Converts the audio buffer precisely into your requested format (`flac`, `wav`, `m4a`, or `mp3`).

4. **Tagging & Art Injection:** Applies custom title/artist metadata and embeds cover art directly into the file header.

5. **Final Output:** Saves the clean, finished audio file to your target directory.

## 🗺️ Roadmap

* \[x\] Core URL conversion pipeline

* \[x\] Support for MP3, M4A, and FLAC

* \[x\] **\[NEW\]** Uncompressed `.wav` export support

* \[x\] Custom metadata tagging (Title, Album, Artist)

* \[x\] Embedded cover art support

* \[ \] Batch playlist downloading mode

* \[ \] Built-in audio normalization filters

## 🤝 Contributing

Contributions make the open-source community an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**!

1. Fork the Project

2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)

3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)

4. Push to the Branch (`git push origin feature/AmazingFeature`)

5. Open a Pull Request

Feel free to check the [Issues](https://github.com/your-username/linktomusic/issues) page to report bugs or suggest new features!

## 📜 License & Acknowledgments

Distributed under the **MIT License**. See [`LICENSE`](LICENSE) for more information.

### 💖 Powered By

* [**yt-dlp**](https://github.com/yt-dlp/yt-dlp) — For robust and reliable media extraction.

* [**FFmpeg**](https://ffmpeg.org/) — For lightning-fast audio transcoding and stream processing.
