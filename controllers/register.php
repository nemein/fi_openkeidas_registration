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
        $this->data['form'] = $this->generate_form($user);

        midgardmvc_core::get_instance()->head->set_title('Rekisteröityminen');
    }

    public function post_form()
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
            return;
        }

        // Read values from POST
        $tmp_user = new fi_openkeidas_registration_user();
        $form = $this->generate_form($tmp_user);
        $form->process_post();

        $user = $this->check_existing($form->memberid->value);

        // Populate user
        midgardmvc_helper_forms_mgdschema::form_to_object($form, $user);

        $password = $this->generate_password();

        $account = $this->create_account($user, $password);

        /*
        midgardmvc_core::get_instance()->authentication->login(array(
            'login' => $account->login,
            'password' => $password
        ));
         */

        $this->send_password($user->email, "{$user->firstname} {$user->lastname}", $password);

        midgardmvc_core::get_instance()->uimessages->add(array(
            'title' => 'Käyttäjätunnus luotu',
            'message' => 'Sinulle on nyt luotu käyttäjätunnus. Saat salasanan pian sähköpostiisi.',
            'type' => 'ok'
        ));
        midgardmvc_core::get_instance()->head->relocate('/mgd:login');
    }

    private function send_password($email, $name, $password)
    {
        $mail = new ezcMailComposer();
        $mail->from = new ezcMailAddress('noreply@openkeidas.fi', 'Open Keidas');
        $mail->addTo(new ezcMailAddress($email, $name));
        $mail->subject = 'Open Keidas -tunnuksesi';
        $mail->plainText = "Hei,\n\nOpen Keidas-salasanasi on: {$password}\n\nVoit käyttää sitä kirjautuaksesi osoitteessa http://openkeidas.fi/mgd:login";
        $mail->build();
        $transport = new ezcMailMtaTransport();
        $transport->send($mail);
    }

    private function generate_form(fi_openkeidas_registration_user $user)
    {
        $form = midgardmvc_helper_forms_mgdschema::create($user, false);

        // Set labels
        $form->firstname->widget->set_label('Etunimi');
        $form->lastname->widget->set_label('Sukunimi');
        $form->memberid->widget->set_label('OAJ:n jäsennumero');
        $form->email->widget->set_label('Sähköposti');
        $form->school->widget->set_label('Koulu');
        $form->municipality->widget->set_label('Kunta');

        // Set all fields to required
        foreach ($form->items as $item)
        {
            $item->set_required(true);
        }

        return $form;
    }

    private function check_existing($memberid)
    {
        $tokens = array(
            'login' => $memberid,
            'authtype' => 'OpenID',
            'active' => true,
            'password' => ''
        );
        $user = new midgard_user($tokens);
        if ($user)
        {
            $person = $user->get_person();
            if ($person)
            {
                return new fi_openkeidas_registration_user($person->guid);
            }
        }
        return new fi_openkeidas_registration_user();
    }

    private function check_email($email)
    {
        $qb = new midgard_query_builder('fi_openkeidas_registration_user');
        $qb->add_constraint('email', '=', $email);
        if ($qb->count() > 0)
        {
            return false;
        }
        return true;
    }

    private function create_account(fi_openkeidas_registration_user $user, $password)
    {
        if (!$this->check_email($user->email))
        {
            midgardmvc_core::get_instance()->uimessages->add(array(
                'title' => 'Käyttäjätunnus olemassa',
                'message' => 'Antamallasi sähköpostiosoitteella on jo käyttäjätunnus. Ole hyvä ja kirjaudu sisään.',
                'type' => 'ok'
            ));
            midgardmvc_core::get_instance()->head->relocate('/mgd:login');
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_registration'); 

        $transaction = new midgard_transaction();
        $transaction->begin();

        $method = 'create';
        if ($user->guid)
        {
            $method = 'update';
        }
        if (!$user->$method())
        {
            $transaction->rollback();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            throw new midgardmvc_exception_httperror('Failed to create user');
        }

        // Typecast to midgard_person
        $person = new midgard_person($user->guid);

        $account = new midgard_user();
        $account->login = $user->email;
        $account->password = sha1($password);
        $account->usertype = 1;
        $account->authtype = 'SHA1';
        $account->active = true;
        $account->set_person($person);
        if (!$account->create())
        {
            $transaction->rollback();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            throw new midgardmvc_exception_httperror('Failed to create user');
        }

        if (!$transaction->commit())
        {
            $transaction->rollback();
            midgardmvc_core::get_instance()->authorization->leave_sudo();
            throw new midgardmvc_exception_httperror('Failed to create user');
        }
        midgardmvc_core::get_instance()->authorization->leave_sudo();
        return $account;
    }

    private function generate_password()
    {
        return substr(hash('sha512', rand()), 0, 6);
    }
}
