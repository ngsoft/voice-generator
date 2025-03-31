# Text to Speech API

## How to use

Piper Binaries for windows and linux are included

Get the voices from https://huggingface.co/rhasspy/piper-voices/tree/main

```shell
git clone https://huggingface.co/rhasspy/piper-voices
```

Copy the langage packs (`*.onnx` and `*.onnx.json`) to `data/voices`

## Routes

**GET** `/voice/list`


**params(optional):**

- `lang`: the lang to search for (`en_EN`, `fr`, ...)



**response:** JSON Object

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "default": false,
      "lang": "fr_FR",
      "localService": false,
      "name": "Piper Siwis [low]#0 - Français (France)",
      "voiceUri": "{voiceUri}"
    }
  ]
}
```

**GET** `/voice/speak/{voice_pack}/{speaker_id}` (you can use one of the resolved `{voiceUri}` in the voice list)


**example**

- `/voice/speak/fr/fr_FR/siwis/low/fr_FR-siwis-low/0`: that is the pack location in `data/voices` without the
  extensions followed by the
  speaker id (`0` if only one speaker in the pack)



**payload** JSON Object

```json
{
  "text": "text to be read."
}
```

**response** `audio/x-wave` 16 bits file to be played
