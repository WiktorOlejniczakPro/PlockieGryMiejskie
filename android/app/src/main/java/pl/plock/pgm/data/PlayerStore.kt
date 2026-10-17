package pl.plock.pgm.data

import android.content.Context

/**
 * Pamięć telefonu: token gracza i ustawienia aplikacji.
 * Token dostajemy raz – przy pierwszym uruchomieniu – i dzięki niemu gracz
 * jest „zalogowany na stałe”, bez ekranu logowania.
 */
class PlayerStore(context: Context) {

    private val prefs = context.getSharedPreferences("pgm", Context.MODE_PRIVATE)

    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) = prefs.edit().putString(KEY_TOKEN, value).apply()

    /** Tryb demo: pozycję gracza ustawia się przytrzymaniem palca na mapie. */
    var demoMode: Boolean
        get() = prefs.getBoolean(KEY_DEMO, false)
        set(value) = prefs.edit().putBoolean(KEY_DEMO, value).apply()

    /** Adres serwera, z którym ostatnio udało się połączyć (albo wpisany ręcznie). */
    var serverUrl: String?
        get() = prefs.getString(KEY_SERVER, null)
        set(value) = prefs.edit().putString(KEY_SERVER, value).apply()

    /** Czy gracz widział już ekran powitalny z zasadami gry. */
    var onboardingSeen: Boolean
        get() = prefs.getBoolean(KEY_ONBOARDING, false)
        set(value) = prefs.edit().putBoolean(KEY_ONBOARDING, value).apply()

    private companion object {
        const val KEY_TOKEN = "player_token"
        const val KEY_DEMO = "demo_mode"
        const val KEY_ONBOARDING = "onboarding_seen"
        const val KEY_SERVER = "server_url"
    }
}
