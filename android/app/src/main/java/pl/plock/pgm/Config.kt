package pl.plock.pgm

import android.os.Build

/**
 * Ustawienia połączenia z serwerem PGM (XAMPP).
 *
 * Adres wybiera się sam:
 *  - emulator Android Studio widzi komputer pod adresem 10.0.2.2,
 *  - prawdziwy telefon na kablu USB łączy się z 127.0.0.1:8080, a komputer
 *    przekierowuje to do XAMPP-a po poleceniu:  adb reverse tcp:8080 tcp:80
 *    (port 8080, bo telefon zwykle nie pozwala adb zająć portu 80)
 *    (trzeba je powtórzyć po każdym odłączeniu kabla / restarcie telefonu).
 *
 * Gdy telefon łączy się przez Wi-Fi zamiast kabla, wpisz w SERVER_OVERRIDE adres IP
 * komputera, np. "http://192.168.1.15/pgm/public/api/v1/" (sprawdzisz go poleceniem ipconfig).
 *
 * Adres MUSI kończyć się ukośnikiem "/".
 */
object Config {
    /** Ręczny adres serwera – null = wybór automatyczny (emulator / USB). */
    private val SERVER_OVERRIDE: String? = null

    const val PATH = "/pgm/public/api/v1/"

    val API_BASE_URL: String = SERVER_OVERRIDE
        ?: if (isEmulator()) "http://10.0.2.2$PATH" else "http://127.0.0.1:8080$PATH"

    /**
     * Adresy sprawdzane po kolei, gdy nie ma połączenia z serwerem
     * (aplikacja sama wybiera ten, który odpowiada, i go zapamiętuje).
     */
    fun candidateUrls(): List<String> = listOfNotNull(
        SERVER_OVERRIDE,
        API_BASE_URL,
        "http://127.0.0.1:8080$PATH",
        "http://localhost:8080$PATH",
        "http://127.0.0.1$PATH",
        "http://10.0.2.2$PATH",
        "http://10.0.2.2:8080$PATH",
    ).distinct()

    /**
     * Adres wpisany przez gracza: "192.168.1.15", "192.168.1.15:8080"
     * albo pełny "http://…/pgm/public/api/v1/" – zawsze zwraca pełny adres API.
     */
    fun normalizeServerUrl(input: String): String? {
        var url = input.trim()
        if (url.isEmpty()) return null
        if (!url.startsWith("http://") && !url.startsWith("https://")) url = "http://$url"
        val afterScheme = url.substringAfter("://")
        if (!afterScheme.contains('/') || afterScheme.substringAfter('/').isBlank()) {
            url = url.trimEnd('/') + PATH
        }
        if (!url.endsWith("/")) url += "/"
        return url
    }

    /** Serwer tras (OSRM na danych OpenStreetMap, darmowy, bez klucza). Musi kończyć się "/". */
    const val ROUTING_BASE_URL = "https://routing.openstreetmap.de/"

    /** Środek mapy, gdy nie znamy jeszcze pozycji gracza (Stary Rynek w Płocku). */
    const val PLOCK_LAT = 52.5463
    const val PLOCK_LNG = 19.6865

    /** Emulator Android Studio – tylko tam dostępny jest tryb demo (pozycja z mapy). */
    fun isEmulator(): Boolean =
        Build.FINGERPRINT.startsWith("generic") ||
            Build.FINGERPRINT.contains("emulator") ||
            Build.HARDWARE.contains("goldfish") ||
            Build.HARDWARE.contains("ranchu") ||
            Build.PRODUCT.contains("sdk") ||
            Build.MODEL.contains("Emulator") ||
            Build.MODEL.contains("Android SDK built for")
}
