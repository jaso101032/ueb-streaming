# UEBTV para Android y Android TV

La app abre directamente la señal continua en `https://uebtv.devs-ueb.tech/senal?modo=tv`. El mismo enlace sigue la emisión en vivo, la parrilla programada y los espacios institucionales, así que no requiere una segunda fuente de video ni modifica el sitio público.

El archivo `public/descargas/uebtv.apk` incluido en el repositorio está firmado
con un certificado **Android Debug**. Es un prototipo para pruebas, no una
versión de distribución. La portada `/senal` muestra un QR y un enlace que
descargan este archivo; antes de distribuirlo como versión definitiva falta
comprobar instalación, reproducción y navegación con mando en dispositivos reales.

El modo TV llena la pantalla, esconde navegación y paneles de gestión, mantiene la pantalla encendida y deja los controles del reproductor accesibles con el mando. La app incluye launcher para Android y Android TV, siempre que el dispositivo tenga Android System WebView actualizado.

## Generar el APK

Abre esta carpeta (`android/`) en Android Studio reciente. Instala Android SDK Platform 36 si el IDE lo solicita y usa JDK 17 o el JDK que incluye Android Studio. Luego ejecuta **Build > Build APK(s)**. El APK de depuración aparecerá en `app/build/outputs/apk/debug/app-debug.apk` y puede instalarse con `adb install -r app/build/outputs/apk/debug/app-debug.apk` o desde una memoria USB en el televisor.

Para cambiar el dominio del sitio antes de compilar, edita `app/src/main/res/values/strings.xml` (`channel_url`). La compilación de producción debe firmarse con una clave propia antes de distribuirla fuera de pruebas.
