import re

file_path = r"c:\xampp\htdocs\barberia\assets\js\i18n.js"

with open(file_path, "r", encoding="utf-8") as f:
    js_content = f.read()

es_str = """
        // Login
        auto_log_5: "Barbería",
        auto_log_6: "Portal del Equipo",
        auto_log_9: "Usuario",
        auto_log_10: "Contraseña",
        auto_log_13: "Ingresar",
        auto_log_15: "© 2026 Barbería Premium - Panel de Staff",
        // Booking
        auto_boo_18: "Barbería",
        auto_boo_19: "← Cancelar",
        auto_boo_23: "Servicios",
        auto_boo_24: "Selección múltiple",
        auto_boo_25: "Menú",
        auto_boo_29: "0 min",
        auto_boo_30: "Elige al menos un servicio",
        // Calendar
        auto_cal_32: "Barbería",
        auto_cal_33: "← Volver",
        auto_cal_36: "Elige tu día",
        auto_cal_37: "📅 Abrir Calendario",
        auto_cal_40: "Elige a tu barbero",
        auto_cal_43: "Horarios disponibles",
        auto_cal_44: "Selecciona una fecha y un barbero primero",
        auto_cal_45: "para ver los horarios disponibles.",
        auto_cal_47: "Continuar al resumen →",
        auto_cal_49: "Mayo 2026",
        auto_cal_50: "Do",
        auto_cal_51: "Lu",
        auto_cal_52: "Ma",
        auto_cal_53: "Mi",
        auto_cal_54: "Ju",
        auto_cal_55: "Vi",
        auto_cal_56: "Sa",
        auto_cal_58: "Hoy",
        auto_cal_59: "Disp.",
        auto_cal_60: "Lleno",
        auto_cal_62: "Hoy",
        auto_cal_63: "Disponible",
        auto_cal_64: "Lleno",
        auto_cal_65: "Pasado",
        // Confirm
        auto_con_67: "Barbería",
        auto_con_68: "← Volver",
        auto_con_69: "Resumen de tu cita",
        auto_con_70: "Servicios",
        auto_con_71: "Barbero",
        auto_con_72: "Fecha y Hora",
        auto_con_73: "Total a pagar",
        auto_con_74: "Al finalizar el servicio",
        auto_con_75: "Tus Datos",
        auto_con_76: "Nombre Completo *",
        auto_con_77: "Teléfono (10 dígitos) *",
        auto_con_78: "Correo Electrónico",
        auto_con_79: "Confirmar Reserva",
        auto_con_81: "¡Reserva Confirmada!",
        auto_con_82: "Tu lugar está asegurado. Te esperamos el",
        auto_con_83: "a las",
        auto_con_84: "Gracias por elegir nuestra Barbería.",
        auto_con_85: "Nos vemos pronto.",
        auto_con_86: "Volver al inicio",
"""

en_str = """
        // Login
        auto_log_5: "Barbershop",
        auto_log_6: "Staff Portal",
        auto_log_9: "Username",
        auto_log_10: "Password",
        auto_log_13: "Login",
        auto_log_15: "© 2026 Premium Barbershop - Staff Panel",
        // Booking
        auto_boo_18: "Barbershop",
        auto_boo_19: "← Cancel",
        auto_boo_23: "Services",
        auto_boo_24: "Multiple selection",
        auto_boo_25: "Menu",
        auto_boo_29: "0 min",
        auto_boo_30: "Choose at least one service",
        // Calendar
        auto_cal_32: "Barbershop",
        auto_cal_33: "← Back",
        auto_cal_36: "Choose your day",
        auto_cal_37: "📅 Open Calendar",
        auto_cal_40: "Choose your barber",
        auto_cal_43: "Available slots",
        auto_cal_44: "Select a date and a barber first",
        auto_cal_45: "to see available timeslots.",
        auto_cal_47: "Continue to summary →",
        auto_cal_49: "May 2026",
        auto_cal_50: "Su",
        auto_cal_51: "Mo",
        auto_cal_52: "Tu",
        auto_cal_53: "We",
        auto_cal_54: "Th",
        auto_cal_55: "Fr",
        auto_cal_56: "Sa",
        auto_cal_58: "Today",
        auto_cal_59: "Avail.",
        auto_cal_60: "Full",
        auto_cal_62: "Today",
        auto_cal_63: "Available",
        auto_cal_64: "Full",
        auto_cal_65: "Past",
        // Confirm
        auto_con_67: "Barbershop",
        auto_con_68: "← Back",
        auto_con_69: "Appointment Summary",
        auto_con_70: "Services",
        auto_con_71: "Barber",
        auto_con_72: "Date and Time",
        auto_con_73: "Total to pay",
        auto_con_74: "Upon completion of service",
        auto_con_75: "Your Details",
        auto_con_76: "Full Name *",
        auto_con_77: "Phone (10 digits) *",
        auto_con_78: "Email Address",
        auto_con_79: "Confirm Booking",
        auto_con_81: "Booking Confirmed!",
        auto_con_82: "Your spot is secured. We look forward to seeing you on",
        auto_con_83: "at",
        auto_con_84: "Thank you for choosing our Barbershop.",
        auto_con_85: "See you soon.",
        auto_con_86: "Return to Home",
"""

js_content = js_content.replace(
    '        admin_auto_v2_80: "Ajustar Imagen",\n    },',
    '        admin_auto_v2_80: "Ajustar Imagen",\n' + es_str + '    },'
)

js_content = js_content.replace(
    '        admin_auto_v2_80: "Adjust Image",\n    }',
    '        admin_auto_v2_80: "Adjust Image",\n' + en_str + '    }'
)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(js_content)

print("Injected public translations into i18n.js")
