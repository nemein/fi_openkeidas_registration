<?php
class fi_openkeidas_registration_controllers_register
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_form()
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
            return;
        }

        $user = new fi_openkeidas_registration_user();
        $this->data['form'] = midgardmvc_helper_forms_mgdschema::create($user, false);

        // Set labels
        $this->data['form']->firstname->widget->set_label('Etunimi');
        $this->data['form']->lastname->widget->set_label('Sukunimi');
        $this->data['form']->memberid->widget->set_label('OAJ:n jäsennumero');
        $this->data['form']->email->widget->set_label('Sähköposti');
        $this->data['form']->school->widget->set_label('Koulu');
        $this->data['form']->municipality->widget->set_label('Kunta');

        // Set all fields to required
        foreach ($this->data['form']->items as $item)
        {
            $item->set_required(true);
        }

        midgardmvc_core::get_instance()->head->set_title('Rekisteröityminen');
    }
}
