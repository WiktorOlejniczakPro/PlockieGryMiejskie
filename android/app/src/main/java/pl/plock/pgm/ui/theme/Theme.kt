package pl.plock.pgm.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColors = lightColorScheme(
    primary = Navy,
    onPrimary = Color.White,
    primaryContainer = BlueLight,
    onPrimaryContainer = NavyDark,
    secondary = Blue,
    onSecondary = Color.White,
    secondaryContainer = BlueLight,
    onSecondaryContainer = Navy,
    tertiary = Amber,
    tertiaryContainer = AmberLight,
    onTertiaryContainer = Color(0xFF5A2E00),
    background = SurfaceLight,
    surface = Color.White,
    surfaceVariant = SurfaceVariantLight,
)

private val DarkColors = darkColorScheme(
    primary = Sky,
    onPrimary = NavyDark,
    primaryContainer = Navy,
    onPrimaryContainer = BlueLight,
    secondary = Sky,
    secondaryContainer = Navy,
    onSecondaryContainer = BlueLight,
    tertiary = Amber,
    tertiaryContainer = Color(0xFF5A2E00),
    onTertiaryContainer = AmberLight,
)

/** Motyw PGM (stałe barwy marki – bez kolorów dynamicznych z tapety). */
@Composable
fun PGMTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = Typography,
        content = content,
    )
}
