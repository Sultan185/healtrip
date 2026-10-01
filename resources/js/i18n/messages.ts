import type { Locale } from '@/features/chat/types';

/*
 * Interface copy. English is the source: the Arabic table is typed against
 * it, so a missing or misspelled key is a compile error.
 */

const en = {
    'app.name': 'HealTrip',
    'app.tagline': 'Patient Decision Assistant',
    'app.newChat': 'New conversation',
    'app.model': 'AI model',
    'app.modelHint': 'Choosing another model starts a new conversation',
    'punct.comma': ', ',
    'app.switchLanguage': 'العربية',
    'app.disclaimer':
        'Guidance only, not a medical diagnosis. In an emergency call your local emergency number.',
    'app.prototype':
        'Prototype with a fictional provider directory. Do not enter real personal health information.',

    'welcome.title': 'Not sure what to do next?',
    'welcome.body':
        'Tell me what is going on. I will help you decide between emergency care, a specialist, or a second opinion, and find matching doctors and hospitals in the HealTrip directory.',
    'welcome.grounding':
        'Every doctor and hospital I show comes from the HealTrip directory. If it is not there, I will say so.',
    'welcome.try': 'Try one of these',
    'welcome.example.1':
        "I have chest pain and I'm not sure whether I should see a cardiologist, go to the ER, or seek a second opinion.",
    'welcome.example.2':
        'I was told I need a knee replacement. I would like a second opinion in Istanbul.',
    'welcome.example.3':
        'I need an Arabic-speaking cardiologist in Cairo for under $100.',
    'welcome.example.4': 'Is there an oncologist in Riyadh?',

    'composer.placeholder': 'Describe your symptoms or what you need…',
    'composer.send': 'Send',
    'composer.hint': 'Enter to send · Shift + Enter for a new line',
    'composer.label': 'Your message',

    'status.thinking': 'Thinking…',
    'status.search_doctors': 'Searching doctors in the directory…',
    'status.search_hospitals': 'Searching hospitals in the directory…',
    'status.present_recommendation': 'Preparing a recommendation…',

    'safety.emergency.title': 'This may be a medical emergency',
    'safety.emergency.body':
        'If this is happening now, call your local emergency number or go to the nearest emergency department immediately. Do not wait for this chat.',
    'safety.emergency.numbers':
        'Emergency numbers: 112 (Türkiye, EU) · 123 (Egypt) · 997 (Saudi Arabia) · 998 (UAE) · 911 (North America)',
    'safety.urgent.title': 'These symptoms can be serious',
    'safety.urgent.body':
        'If they are severe, come on suddenly, or come with shortness of breath, sweating, fainting or pain spreading to the arm or jaw, call your local emergency number now.',

    'urgency.emergency': 'Emergency',
    'urgency.urgent': 'Urgent',
    'urgency.routine': 'Routine',
    'urgency.emergency.hint': 'Seek emergency care now',
    'urgency.urgent.hint': 'Be seen within about a day',
    'urgency.routine.hint': 'Can be scheduled',

    'carePath.emergency_care': 'Go to emergency care',
    'carePath.specialist_visit': 'See a specialist',
    'carePath.second_opinion': 'Get a second opinion',
    'carePath.primary_care': 'Start with a general doctor',
    'carePath.telemedicine': 'Book a remote consultation',

    'card.title': 'Recommended next step',
    'card.doctors': 'Matching doctors',
    'card.hospitals': 'Hospitals',
    'card.verified': 'Verified from the HealTrip directory',
    'card.id': 'ID {id}',
    'doctor.prefix': 'Dr.',
    'doctor.experience': '{years} yrs experience',
    'doctor.fee': '${fee} consultation',
    'doctor.nextAvailable': 'Next available {date}',
    'doctor.secondOpinion': 'Second opinion',
    'doctor.telemedicine': 'Remote consultation',
    'doctor.speaks': 'Speaks',
    'hospital.emergency': '24/7 emergency',
    'hospital.noEmergency': 'No emergency department',
    'hospital.phone': 'Phone',
    'hospital.emergencyPhone': 'Emergency line',
    'hospital.patientDesk': 'International patient desk',

    'language.ar': 'Arabic',
    'language.en': 'English',
    'language.tr': 'Turkish',
    'language.de': 'German',
    'language.fr': 'French',

    'trace.title': 'How this answer was produced',
    'trace.steps': '{count} steps',
    'trace.safety': 'Safety screen',
    'trace.safety.clear': 'No emergency language detected',
    'trace.safety.flagged': 'Flagged: {categories}',
    'trace.tool.search_doctors': 'Searched doctors',
    'trace.tool.search_hospitals': 'Searched hospitals',
    'trace.tool.present_recommendation': 'Presented recommendation',
    'trace.result.matches': '{returned} shown of {total} matching',
    'trace.result.none': 'No matches in the directory',
    'trace.result.displayed': 'Card rendered from database records',
    'trace.result.running': 'Running…',
    'trace.error.ungrounded_ids':
        'Rejected: the model referenced a provider no search returned',
    'trace.error.unverified_summary':
        'Rejected: the summary contained unverified details',
    'trace.error.invalid_input': 'Rejected: invalid tool input',
    'trace.error.specialty_mismatch':
        'Rejected: the doctors do not practise the specialty on the card',
    'trace.error.inconsistent_recommendation':
        'Rejected: emergency must go to emergency care',
    'trace.error.tool_failed':
        'Failed: internal error (logged); the model was told not to guess',
    'trace.error.other': 'Tool error: {code}',
    'trace.guard.redraft':
        'Output check withheld a draft with unverified details; the model rewrote it',
    'trace.guard.blocked':
        'Output check blocked the reply; a safe message was shown instead',
    'trace.filters.none': 'no filters',
    'trace.model': 'Model',

    'error.title': 'Something went wrong',
    'error.retry': 'Try again',
    'error.resync': 'Reload conversation',
    'error.not_configured':
        'The assistant is not configured yet: the server has no API key for this AI model.',
    'error.auth': 'The server could not authenticate with the model provider.',
    'error.rate_limited':
        'Too many requests right now. Please wait a moment and try again.',
    'error.unavailable':
        'The assistant is temporarily unavailable. Please try again.',
    'error.turn_in_progress':
        'A reply is still being generated. Please wait for it to finish.',
    'error.conversation_full':
        'This conversation has reached its length limit. Please start a new one.',
    'error.session_expired':
        'Your session expired. Reload the page to continue.',
    'error.not_found':
        'This conversation is no longer available. Please start a new one.',
    'error.connection_lost':
        'The connection was lost. Your message may still have been answered.',
    'error.invalid': 'That message could not be sent. Please shorten it.',
    'error.internal': 'An unexpected error occurred. Please try again.',
} as const;

export type MessageKey = keyof typeof en;

const ar: Record<MessageKey, string> = {
    'app.name': 'HealTrip',
    'app.tagline': 'مساعد قرار المريض',
    'app.newChat': 'محادثة جديدة',
    'app.model': 'نموذج الذكاء الاصطناعي',
    'app.modelHint': 'اختيار نموذج آخر يبدأ محادثة جديدة',
    'punct.comma': '، ',
    'app.switchLanguage': 'English',
    'app.disclaimer':
        'إرشاد فقط وليس تشخيصًا طبيًا. في الحالات الطارئة اتصل برقم الطوارئ المحلي.',
    'app.prototype':
        'نموذج أولي بدليل مقدمي خدمة افتراضي. لا تُدخل بيانات صحية شخصية حقيقية.',

    'welcome.title': 'لست متأكدًا من الخطوة التالية؟',
    'welcome.body':
        'أخبرني بما تشعر به. سأساعدك على الاختيار بين الطوارئ أو زيارة طبيب متخصص أو طلب رأي ثانٍ، وأعرض عليك أطباء ومستشفيات مناسبة من دليل HealTrip.',
    'welcome.grounding':
        'كل طبيب أو مستشفى أعرضه مصدره دليل HealTrip. وإن لم يكن موجودًا فيه فسأخبرك بذلك.',
    'welcome.try': 'جرّب أحد هذه الأمثلة',
    'welcome.example.1':
        'عندي ألم في الصدر ولا أعرف هل أذهب لطبيب قلب أم للطوارئ أم أطلب رأيًا ثانيًا.',
    'welcome.example.2':
        'قيل لي إنني أحتاج إلى استبدال مفصل الركبة وأريد رأيًا ثانيًا في إسطنبول.',
    'welcome.example.3':
        'أبحث عن طبيب قلب يتحدث العربية في القاهرة بأقل من 100 دولار.',
    'welcome.example.4': 'هل يوجد طبيب أورام في الرياض؟',

    'composer.placeholder': 'صف الأعراض أو ما تحتاج إليه…',
    'composer.send': 'إرسال',
    'composer.hint': 'Enter للإرسال · Shift + Enter لسطر جديد',
    'composer.label': 'رسالتك',

    'status.thinking': 'جارٍ التفكير…',
    'status.search_doctors': 'جارٍ البحث عن أطباء في الدليل…',
    'status.search_hospitals': 'جارٍ البحث عن مستشفيات في الدليل…',
    'status.present_recommendation': 'جارٍ إعداد التوصية…',

    'safety.emergency.title': 'قد تكون هذه حالة طبية طارئة',
    'safety.emergency.body':
        'إذا كان هذا يحدث الآن فاتصل برقم الطوارئ المحلي أو توجه إلى أقرب قسم طوارئ فورًا. لا تنتظر هذه المحادثة.',
    'safety.emergency.numbers':
        'أرقام الطوارئ: 112 (تركيا وأوروبا) · 123 (مصر) · 997 (السعودية) · 998 (الإمارات) · 911 (أمريكا الشمالية)',
    'safety.urgent.title': 'هذه الأعراض قد تكون خطيرة',
    'safety.urgent.body':
        'إذا كانت شديدة أو ظهرت فجأة أو صاحبها ضيق في التنفس أو تعرّق أو إغماء أو ألم يمتد إلى الذراع أو الفك، فاتصل برقم الطوارئ المحلي الآن.',

    'urgency.emergency': 'طارئ',
    'urgency.urgent': 'عاجل',
    'urgency.routine': 'غير عاجل',
    'urgency.emergency.hint': 'توجه إلى الطوارئ الآن',
    'urgency.urgent.hint': 'يُفضّل الكشف خلال يوم تقريبًا',
    'urgency.routine.hint': 'يمكن حجز موعد عادي',

    'carePath.emergency_care': 'توجه إلى الطوارئ',
    'carePath.specialist_visit': 'زيارة طبيب متخصص',
    'carePath.second_opinion': 'اطلب رأيًا ثانيًا',
    'carePath.primary_care': 'ابدأ بطبيب عام',
    'carePath.telemedicine': 'احجز استشارة عن بُعد',

    'card.title': 'الخطوة التالية المقترحة',
    'card.doctors': 'أطباء مناسبون',
    'card.hospitals': 'مستشفيات',
    'card.verified': 'بيانات موثّقة من دليل HealTrip',
    'card.id': 'رقم {id}',
    'doctor.prefix': 'د.',
    'doctor.experience': 'خبرة {years} سنة',
    'doctor.fee': 'الكشف {fee} دولار',
    'doctor.nextAvailable': 'أقرب موعد {date}',
    'doctor.secondOpinion': 'رأي ثانٍ',
    'doctor.telemedicine': 'استشارة عن بُعد',
    'doctor.speaks': 'يتحدث',
    'hospital.emergency': 'طوارئ 24 ساعة',
    'hospital.noEmergency': 'لا يوجد قسم طوارئ',
    'hospital.phone': 'الهاتف',
    'hospital.emergencyPhone': 'خط الطوارئ',
    'hospital.patientDesk': 'مكتب المرضى الدوليين',

    'language.ar': 'العربية',
    'language.en': 'الإنجليزية',
    'language.tr': 'التركية',
    'language.de': 'الألمانية',
    'language.fr': 'الفرنسية',

    'trace.title': 'كيف تم إعداد هذه الإجابة',
    'trace.steps': '{count} خطوات',
    'trace.safety': 'فحص الأمان',
    'trace.safety.clear': 'لم تُرصد عبارات تدل على حالة طارئة',
    'trace.safety.flagged': 'تم الرصد: {categories}',
    'trace.tool.search_doctors': 'بحث عن أطباء',
    'trace.tool.search_hospitals': 'بحث عن مستشفيات',
    'trace.tool.present_recommendation': 'عرض التوصية',
    'trace.result.matches': 'عُرض {returned} من {total} نتيجة مطابقة',
    'trace.result.none': 'لا توجد نتائج مطابقة في الدليل',
    'trace.result.displayed': 'تم عرض البطاقة من سجلات قاعدة البيانات',
    'trace.result.running': 'جارٍ التنفيذ…',
    'trace.error.ungrounded_ids':
        'مرفوض: أشار النموذج إلى مقدم خدمة لم يظهر في أي بحث',
    'trace.error.unverified_summary': 'مرفوض: الملخص تضمّن تفاصيل غير موثّقة',
    'trace.error.invalid_input': 'مرفوض: مدخلات الأداة غير صحيحة',
    'trace.error.specialty_mismatch':
        'مرفوض: الأطباء لا يمارسون التخصص المكتوب على البطاقة',
    'trace.error.inconsistent_recommendation':
        'مرفوض: الحالة الطارئة يجب أن تُوجَّه إلى الطوارئ',
    'trace.error.tool_failed':
        'فشل: خطأ داخلي (تم تسجيله)، وأُبلغ النموذج بألا يخمّن النتيجة',
    'trace.error.other': 'خطأ في الأداة: {code}',
    'trace.guard.redraft':
        'فحص المخرجات حجب مسودة فيها تفاصيل غير موثّقة، وأعاد النموذج كتابتها',
    'trace.guard.blocked': 'فحص المخرجات حجب الرد وعُرضت رسالة آمنة بدلًا منه',
    'trace.filters.none': 'بدون فلاتر',
    'trace.model': 'النموذج',

    'error.title': 'حدث خطأ',
    'error.retry': 'حاول مرة أخرى',
    'error.resync': 'إعادة تحميل المحادثة',
    'error.not_configured':
        'المساعد غير مُهيأ بعد: لا يوجد على الخادم مفتاح API لهذا النموذج.',
    'error.auth': 'تعذّر على الخادم التحقق من هويته لدى مزوّد النموذج.',
    'error.rate_limited': 'طلبات كثيرة الآن. انتظر قليلًا ثم حاول مرة أخرى.',
    'error.unavailable': 'المساعد غير متاح مؤقتًا. حاول مرة أخرى.',
    'error.turn_in_progress': 'ما زال الرد قيد الإعداد. انتظر حتى ينتهي.',
    'error.conversation_full':
        'وصلت هذه المحادثة إلى الحد الأقصى. ابدأ محادثة جديدة.',
    'error.session_expired': 'انتهت الجلسة. أعد تحميل الصفحة للمتابعة.',
    'error.not_found': 'هذه المحادثة لم تعد متاحة. ابدأ محادثة جديدة.',
    'error.connection_lost': 'انقطع الاتصال. ربما تم الرد على رسالتك بالفعل.',
    'error.invalid': 'تعذّر إرسال الرسالة. حاول اختصارها.',
    'error.internal': 'حدث خطأ غير متوقع. حاول مرة أخرى.',
};

export const messages: Record<Locale, Record<MessageKey, string>> = { en, ar };

/** Narrow a key built at runtime (e.g. from a server-sent code). */
export function isMessageKey(key: string): key is MessageKey {
    return key in en;
}

/** Labels for the safety screen's categories (RedFlagScreener.php). */
export const safetyCategories: Record<string, Record<Locale, string>> = {
    cardiac: { en: 'possible cardiac emergency', ar: 'اشتباه طارئ قلبي' },
    stroke: { en: 'possible stroke', ar: 'اشتباه سكتة دماغية' },
    breathing: { en: 'difficulty breathing', ar: 'صعوبة في التنفس' },
    bleeding: { en: 'severe bleeding', ar: 'نزيف شديد' },
    consciousness: { en: 'loss of consciousness', ar: 'فقدان الوعي' },
    self_harm: { en: 'risk of self-harm', ar: 'خطر إيذاء النفس' },
    allergic_reaction: {
        en: 'severe allergic reaction',
        ar: 'حساسية شديدة',
    },
    chest_pain: { en: 'chest pain', ar: 'ألم في الصدر' },
    palpitations: { en: 'palpitations', ar: 'خفقان' },
    severe_pain: { en: 'severe pain', ar: 'ألم شديد' },
};
