package pl.plock.pgm

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.lifecycle.viewmodel.compose.viewModel
import org.osmdroid.config.Configuration
import pl.plock.pgm.ui.AppViewModel
import pl.plock.pgm.ui.PgmApp
import pl.plock.pgm.ui.theme.PGMTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // osmdroid (mapy OpenStreetMap): pamięć podręczna kafelków i identyfikacja
        // aplikacji – wymagana przez zasady korzystania z serwerów OSM
        Configuration.getInstance().load(this, getSharedPreferences("osmdroid", MODE_PRIVATE))
        Configuration.getInstance().userAgentValue = packageName

        enableEdgeToEdge()
        setContent {
            PGMTheme {
                val vm: AppViewModel = viewModel()
                PgmApp(vm)
            }
        }
    }
}
